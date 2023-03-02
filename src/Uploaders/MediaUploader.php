<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\MediaLibraryUploads\ConstrainedFileAdder;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

abstract class MediaUploader extends Uploader
{
    public $mediaName;

    public $collection;

    public $displayConversions;

    public $order;

    public function __construct(array $field, array $configuration)
    {
        parent::__construct($field, $configuration);

        $this->collection = $configuration['collection'] ?? 'default';

        $modelDefinition = $this->getMediaCollectionFromModel();

        $this->displayConversions = $configuration['displayConversions'] ?? [];
        $this->displayConversions = (array) $this->displayConversions;

        $this->eventsModel = $field['eventsModel'];

        $this->disk = $modelDefinition?->diskName ?? null;
        $this->disk = empty($this->disk) ? $configuration['disk'] ?? $field['disk'] ?? config('media-library.disk_name') : $this->disk;

        $this->mediaName = $configuration['mediaName'] ?? $this->fieldName;
        $this->isMultiple = false;
    }

    abstract public function save(Model $entry, $value = null);

    protected function getPreviousRepeatableMedia(Model $entry)
    {
        return $this->get($entry)->transform(function ($item) {
            return [$this->fieldName => $item, 'order_column' => $item->getCustomProperty('repeatableRow')];
        })->sortBy('order_column')->keyBy('order_column')->toArray();
    }

    protected function getPreviousRepeatableValues(Model $entry)
    {
        if ($this->isMultiple) {
            return $this->get($entry)
                        ->groupBy(function($item) {
                            return $item->getCustomProperty('repeatableRow');
                        })
                        ->transform(function ($media) use ($entry) {
                            $mediaItems = $media->map(function($item) use ($entry) {
                                return $this->getMediaIdentifier($item, $entry);
                            })
                            ->toArray();

                            return [$this->fieldName => $mediaItems];
                        })
                        ->toArray();
        }

        return $this->get($entry)
                    ->transform(function ($item) use ($entry) {
                        return [
                            $this->fieldName => $this->getMediaIdentifier($item, $entry), 
                            'order_column' => $item->getCustomProperty('repeatableRow')
                        ];
                    })
                    ->sortBy('order_column')
                    ->keyBy('order_column')
                    ->toArray();
    }

    public function get(Model $entry)
    {
        if ($this->isRepeatable || $this->isMultiple) {
            return $entry->getMedia($this->collection, function ($media) {
                return $media->getCustomProperty('fieldName') === $this->fieldName && $media->getCustomProperty('parentField') === $this->parentField;
            });
        }

        return $entry->getFirstMedia($this->collection, function ($media) {
            return $media->getCustomProperty('fieldName') === $this->fieldName && $media->getCustomProperty('parentField') === $this->parentField;
        });
    }

    public function processFileUpload(Model $entry)
    {
        if (is_a($this, \Backpack\MediaLibraryUploads\Uploaders\MediaRepeatableUploads::class)) {
            $entry->{$this->fieldName} = json_encode($this->save($entry));
        } else {
            $this->save($entry);
            $entry->offsetUnset($this->fieldName);
        }

        return $entry;
    }

    public function retrieveUploadedFile(Model $entry)
    {
        $crudField = CRUD::field($this->fieldName)->disk($this->disk)->prefix($this->path);

        if ($this->temporary) {
            $crudField->temporary($this->temporary)->expiration($this->expiration);
        }

        $media = $this->get($entry);

        if (! $media) {
            return null;
        }
        
        if (empty($entry->mediaConversions)) {
            $entry->registerAllMediaConversions();
        }

        if (is_a($media, 'Spatie\MediaLibrary\MediaCollections\Models\Media')) {
            $entry->{$this->fieldName} = $this->getMediaIdentifier($media, $entry);
        } else {
            $entry->{$this->fieldName} = $media->map(function ($item) use ($entry) {
                return $this->getMediaIdentifier($item, $entry);
            })->toArray();
        }

        return $entry;
    }

    protected function addMediaFile($entry, $file, $order = null)
    {
        $this->order = $order;

        $fileAdder = is_a($file, UploadedFile::class, true) ? $entry->addMedia($file) : $entry->addMediaFromBase64($file);

        $fileAdder = $fileAdder->usingName($this->mediaName)
                                ->withCustomProperties($this->getCustomProperties())
                                ->usingFileName($this->getFileName($file).'.'.$this->getExtensionFromFile($file));

        $constrainedMedia = new ConstrainedFileAdder();
        $constrainedMedia->setFileAdder($fileAdder);
        $constrainedMedia->setMediaUploader($this);

        if ($this->savingEventCallback && is_callable($this->savingEventCallback)) {
            $constrainedMedia = call_user_func_array($this->savingEventCallback, [$constrainedMedia, $this]);
        }

        if(!$constrainedMedia) {
            throw new Exception('Please return a valid class from `whenSaving` closure on field: ' . $this->fieldName);
        }

        $constrainedMedia->getFileAdder()->toMediaCollection($this->collection, $this->disk);
    }

    public function getCustomProperties()
    {
        return ['fieldName' => $this->fieldName, 'parentField' => $this->parentField, 'repeatableRow' => $this->order];
    }

    protected function getMediaIdentifier($media, $entry = null)
    {
        $path = PathGeneratorFactory::create($media);

        if ($entry && ! empty($entry->mediaConversions)) {
            $conversion = array_filter($entry->mediaConversions, function ($item) use ($media) {
                return $item->getName() === $this->getConversionToDisplay($media);
            })[0] ?? null;

            if (! $conversion) {
                return $path->getPath($media).$media->file_name;
            }

            return $path->getPathForConversions($media).$conversion->getConversionFile($media);
        }

        return $path->getPath($media).$media->file_name;
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

    private function getMediaCollectionFromModel()
    {
        return $this->modelInstance()->getRegisteredMediaCollections()
                                ->reject(function ($item) {
                                    $item->name !== $this->collection;
                                })
                                ->first();
    }
}
