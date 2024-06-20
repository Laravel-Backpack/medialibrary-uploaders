<?php

namespace Backpack\MediaLibraryUploaders\Uploaders;

use Backpack\CRUD\app\Library\Uploaders\Uploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\File\File;

abstract class MediaUploader extends Uploader
{
    use Traits\IdentifiesMedia;
    use Traits\AddMediaToModels;

    public $mediaName;

    public $collection;

    public $displayConversions;

    public $order;

    public $savingEventCallback = null;

    public function __construct(array $crudObject, array $configuration)
    {
        $this->collection = $configuration['collection'] ?? 'default';
        $this->mediaName = $configuration['mediaName'] ?? $crudObject['name'];
        $this->savingEventCallback = $configuration['whenSaving'] ?? null;

        $this->displayConversions = $configuration['displayConversions'] ?? [];
        $this->displayConversions = (array) $this->displayConversions;

        $modelDefinition = $this->getModelInstance($crudObject)->getRegisteredMediaCollections()
                            ->reject(function ($item) {
                                $item->name !== $this->collection;
                            })
                            ->first();

        $configuration['disk'] ??= $modelDefinition?->diskName ?? null;

        $configuration['disk'] = empty($configuration['disk']) ? ($crudObject['disk'] ?? config('media-library.disk_name')) : $configuration['disk'];
        //dd($configuration['disk'], $crudObject);
        // read https://spatie.be/docs/laravel-medialibrary/v11/advanced-usage/using-a-custom-directory-structure#main
        // on how to customize file directory
        $crudObject['prefix'] = $configuration['path'] = '';

        parent::__construct($crudObject, $configuration);
    }

    /*************************
     *     Public methods    *
     *************************/
    public function storeUploadedFiles(Model $entry): Model
    {
        if ($this->handleRepeatableFiles) {
            return $this->handleRepeatableFiles($entry);
        }

        $this->uploadFiles($entry);

        // make sure we remove the attribute from the model in case developer is using it in fillable
        // or using guarded in their models.
        $entry->offsetUnset($this->getName());
        // setting the raw attributes makes sure the `attributeCastCache` property is cleared, preventing
        // uploaded files from being re-added to the entry from the cache.
        $entry = $entry->setRawAttributes($entry->getAttributes());

        return $entry;
    }

    public function retrieveUploadedFiles(Model $entry): Model
    { 
        $media = $this->getPreviousFiles($entry);

        if (! $media) {
            return $entry;
        }

        if (empty($entry->mediaConversions)) {
            $entry->registerAllMediaConversions();
        }

        if ($this->handleRepeatableFiles) {
            $values = $entry->{$this->getRepeatableContainerName()} ?? [];

            if (! is_array($values)) {
                $values = json_decode($values, true);
            }

            $repeatableUploaders = array_merge(app('UploadersRepository')->getRepeatableUploadersFor($this->getRepeatableContainerName()), [$this]);
            foreach ($repeatableUploaders as $uploader) {
                $uploadValues = $uploader->getPreviousRepeatableValues($entry);

                $values = $this->mergeValuesRecursive($values, $uploadValues);
            }

            $entry->{$this->getRepeatableContainerName()} = $values;

            return $entry;
        }

        if (is_a($media, 'Spatie\MediaLibrary\MediaCollections\Models\Media')) {
            $entry->{$this->getName()} = $this->getMediaIdentifier($media, $entry);
        } else {
            $entry->{$this->getName()} = $media->map(function ($item) use ($entry) {
                return $this->getMediaIdentifier($item, $entry);
            })->toArray();
        }

        return $entry;
    }

    /*****************************************************
     *     Protected methods - default implementation    *
     *****************************************************/
    protected function get(HasMedia|Model $entry)
    {
        $media = $entry->getMedia($this->collection, function ($media) use ($entry) {
            /** @var Media $media */
            return $media->getCustomProperty('name') === $this->getName() && $media->getCustomProperty('repeatableContainerName') === $this->repeatableContainerName && $entry->{$entry->getKeyName()} === $media->getAttribute('model_id');
        });

        if ($this->canHandleMultipleFiles() || $this->handleRepeatableFiles) {
            return $media;
        }

        return $media->first();
    }

    public function getPreviousFiles(Model $entry): mixed
    {
        $media = $entry->getMedia($this->collection, function ($media) use ($entry) {
            /** @var Media $media */
            return $media->getCustomProperty('name') === $this->getName() && 
                    $media->getCustomProperty('repeatableContainerName') === $this->repeatableContainerName && 
                    $entry->{$entry->getKeyName()} === $media->getAttribute('model_id');
        });

        if ($this->canHandleMultipleFiles() || $this->handleRepeatableFiles) {
            return $media;
        }
       
        return $media->first();
    }

    protected function processRepeatableUploads(Model $entry, Collection $values): array
    {
        foreach (app('UploadersRepository')->getRepeatableUploadersFor($this->getRepeatableContainerName()) as $uploader) {
            $uploader->uploadRepeatableFiles($values->pluck($uploader->getName())->toArray(), $uploader->getPreviousRepeatableMedia($entry), $entry);

            $values->transform(function ($item) use ($uploader) {
                unset($item[$uploader->getName()]);

                return $item;
            });
        }

        return $values->toArray();
    }

    protected function getPreviousRepeatableValues(Model $entry)
    {
        if ($this->canHandleMultipleFiles()) {
            return $this->get($entry)
                        ->groupBy(function ($item) {
                            return $item->getCustomProperty('repeatableRow');
                        })
                        ->transform(function ($media) use ($entry) {
                            $mediaItems = $media->map(function ($item) use ($entry) {
                                return $this->getMediaIdentifier($item, $entry);
                            })
                            ->toArray();

                            return [$this->getName() => $mediaItems];
                        })
                        ->toArray();
        }

        return $this->get($entry)
                    ->transform(function ($item) use ($entry) {
                        return [
                            $this->getName() => $this->getMediaIdentifier($item, $entry),
                            'order_column'   => $item->getCustomProperty('repeatableRow'),
                        ];
                    })
                    ->sortBy('order_column')
                    ->keyBy('order_column')
                    ->toArray();
    }

    protected function getPreviousRepeatableMedia(Model $entry)
    {
        $orderedMedia = [];
        $previousMedia = $this->get($entry)->transform(function ($item) {
            return [$this->getName() => $item, 'order_column' => $item->getCustomProperty('repeatableRow')];
        });
        $previousMedia->each(function ($item) use (&$orderedMedia) {
            $orderedMedia[] = $item[$this->getName()];
        });

        return $orderedMedia;
    }

    /**************************************************
     *     Private methods- default implementation    *
     **************************************************/

    private function getModelInstance($crudObject): Model
    {
        return new ($crudObject['baseModel'] ?? get_class(app('crud')->getModel()));
    }

    private function initFileAdder($entry, $file)
    {
        if (is_a($file, UploadedFile::class, true)) {
            return $entry->addMedia($file);
        }

        if (is_string($file)) {
            return $entry->addMediaFromBase64($file);
        }

        if (get_class($file) === File::class) {
            return $entry->addMedia($file->getPathName());
        }
    }

    private function getConversionToDisplay($item)
    {
        foreach ($this->displayConversions as $displayConversion) {
            if ($item->hasGeneratedConversion($displayConversion)) {
                return $displayConversion;
            }
        }

        return false;
    }

    /*************************
     *     Helper methods    *
     *************************/
    public function getCustomProperties()
    {
        return [
            'name'                    => $this->getName(),
            'repeatableContainerName' => $this->repeatableContainerName,
            'repeatableRow'           => $this->order,
        ];
    }
}
