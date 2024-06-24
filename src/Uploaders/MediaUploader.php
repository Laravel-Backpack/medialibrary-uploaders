<?php

namespace Backpack\MediaLibraryUploaders\Uploaders;

use Backpack\CRUD\app\Library\Uploaders\Uploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

abstract class MediaUploader extends Uploader
{
    use Traits\IdentifiesMedia;
    use Traits\AddMediaToModels;
    use Traits\HasConstrainedFileAdder;
    use Traits\HasMediaName;
    use Traits\HasCustomProperties;
    use Traits\HasSavingCallback;
    use Traits\HasCollections;
    use Traits\RetrievesUploadedFiles;

    public $displayConversions;

    public $order;

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

    /** @deprecated - use getPreviousFiles() */
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

    

    /**************************************************
     *     Private methods- default implementation    *
     **************************************************/

    private function getModelInstance($crudObject): Model
    {
        return new ($crudObject['baseModel'] ?? get_class(app('crud')->getModel()));
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
}
