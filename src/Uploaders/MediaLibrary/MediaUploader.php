<?php

namespace Backpack\MediaLibraryUploads\Uploaders\MediaLibrary;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\MediaLibraryUploads\ConstrainedFileAdder;
use Backpack\MediaLibraryUploads\Uploaders\Uploader;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

abstract class MediaUploader extends Uploader
{
    public $mediaName;

    public $collection;

    public $displayConversions;

    public $order;

    public $savingEventCallback = null;

    public function __construct(array $crudObject, array $configuration)
    {
        $this->entryClass = $crudObject['entryClass'];

        $this->collection = $configuration['collection'] ?? 'default';
        $this->mediaName = $configuration['mediaName'] ?? $crudObject['name'];
        $this->savingEventCallback = $configuration['whenSaving'] ?? null;

        $this->displayConversions = $configuration['displayConversions'] ?? [];
        $this->displayConversions = (array) $this->displayConversions;

        $modelDefinition = (new $this->entryClass)->getRegisteredMediaCollections()
                            ->reject(function ($item) {
                                $item->name !== $this->collection;
                            })
                            ->first();

        $configuration['disk'] = $modelDefinition?->diskName ?? null;

        $configuration['disk'] = empty($configuration['disk']) ? $crudObject['disk'] ?? config('media-library.disk_name') : null;

        // read https://spatie.be/docs/laravel-medialibrary/v10/advanced-usage/using-a-custom-directory-structure#main
        // on how to customize file directory
        $crudObject['prefix'] = $configuration['path'] = '';

        parent::__construct($crudObject, $configuration);
    }

    abstract public function save(Model $entry, $value = null);

    protected function getPreviousRepeatableMedia(Model $entry)
    {
        return $this->get($entry)->transform(function ($item) {
            return [$this->name => $item, 'order_column' => $item->getCustomProperty('repeatableRow')];
        })->sortBy('order_column')->keyBy('order_column')->toArray();
    }

    public function getPreviousRepeatableValues(Model $entry)
    {
        if ($this->isMultiple) {
            return $this->get($entry)
                        ->groupBy(function ($item) {
                            return $item->getCustomProperty('repeatableRow');
                        })
                        ->transform(function ($media) use ($entry) {
                            $mediaItems = $media->map(function ($item) use ($entry) {
                                return $this->getMediaIdentifier($item, $entry);
                            })
                            ->toArray();

                            return [$this->name => $mediaItems];
                        })
                        ->toArray();
        }

        return $this->get($entry)
                    ->transform(function ($item) use ($entry) {
                        return [
                            $this->name => $this->getMediaIdentifier($item, $entry),
                            'order_column'   => $item->getCustomProperty('repeatableRow'),
                        ];
                    })
                    ->sortBy('order_column')
                    ->keyBy('order_column')
                    ->toArray();
    }

    public function get(Model $entry)
    {
        if ($this->isMultiple || $this->isRepeatable) {
            return $entry->getMedia($this->collection, function ($media) use ($entry) {
                return $media->getCustomProperty('name') === $this->name && $media->getCustomProperty('parentField') === $this->parentField && $entry->id === $media->model_id;
            });
        }

        return $entry->getFirstMedia($this->collection, function ($media) use ($entry) {
            return $media->getCustomProperty('name') === $this->name && $media->getCustomProperty('parentField') === $this->parentField && $entry->id === $media->model_id;
        });
    }

    public function processFileUpload(Model $entry)
    {
        if (is_a($this, \Backpack\MediaLibraryUploads\Uploaders\MediaRepeatable::class) && ! $this->isRelationship) {
            $entry->{$this->name} = json_encode($this->save($entry));
        } else {
            $this->save($entry);
            $entry->offsetUnset($this->name);
        }

        return $entry;
    }

    public function retrieveUploadedFile(Model $entry)
    {
        $this->setupUploadConfigsInCrudObject(CRUD::{$this->crudObjectType}($this->name));
        
        $media = $this->get($entry);
     
        if (! $media) {
            return null;
        }

        if (empty($entry->mediaConversions)) {
            $entry->registerAllMediaConversions();
        }

        if (is_a($media, 'Spatie\MediaLibrary\MediaCollections\Models\Media')) {
            $entry->{$this->name} = $this->getMediaIdentifier($media, $entry);
        } else {
            $entry->{$this->name} = $media->map(function ($item) use ($entry) {
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
                                ->usingFileName($this->getFileNameWithExtension($file));

        $constrainedMedia = new ConstrainedFileAdder();
        $constrainedMedia->setFileAdder($fileAdder);
        $constrainedMedia->setMediaUploader($this);

        if ($this->savingEventCallback && is_callable($this->savingEventCallback)) {
            $constrainedMedia = call_user_func_array($this->savingEventCallback, [$constrainedMedia, $this]);
        }

        if (! $constrainedMedia) {
            throw new Exception('Please return a valid class from `whenSaving` closure on field: '.$this->name);
        }

        $constrainedMedia->getFileAdder()->toMediaCollection($this->collection, $this->disk);
    }

    public function getCustomProperties()
    {
        return ['name' => $this->name, 'parentField' => $this->parentField, 'repeatableRow' => $this->order];
    }

    public function getMediaIdentifier($media, $entry = null)
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
}
