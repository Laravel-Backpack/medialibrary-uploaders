<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\MediaLibraryUploads\ConstrainedFileAdder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

abstract class MediaUploader extends Uploader
{
    public $mediaName;

    public $collection;

    public $displayConversions;

    public $keepOriginalConversionFileExtension;

    public function __construct(array $field, array $configuration)
    {
        parent::__construct($field, $configuration);

        $this->collection = $configuration['collection'] ?? 'default';
        $this->keepOriginalConversionFileExtension = $configuration['keepOriginalConversionFileExtension'] ?? true;

        
        $modelDefinition = $this->getMediaCollectionFromModel();

        $this->displayConversions = $configuration['displayConversions'] ?? [];
        $this->displayConversions = (array)$this->displayConversions;

        $this->eventsModel = $field['eventsModel'];  
        
        $this->disk = $modelDefinition?->diskName ?? null;
        $this->disk = empty($this->disk) ? $configuration['disk'] ?? config('media-library.disk_name') : $this->disk;

        $this->mediaName = $configuration['name'] ?? $this->fieldName;
        $this->isMultiple = $modelDefinition?->singleFile ?? $configuration['singleFile'] ?? false;
    }

    abstract public function save(Model $entry, $value = null);

    protected function getPreviousRepeatableValues(Model $entry)
    {
        return $this->get($entry)->transform(function ($item) {
            return [$this->fieldName => $this->getMediaIdentifier($item), 'order_column' => $item->order_column];
        })->sortBy('order_column')->keyBy('order_column')->toArray();
    }

    public function get(Model $entry)
    {
        if ($this->isRepeatable || $this->isMultiple) {
            return $entry->getMedia($this->collection, function ($media) {
                return $media->name === $this->mediaName;
            });
        }

        return $entry->getFirstMedia($this->collection, function ($media) {
            return $media->name === $this->mediaName;
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
        
        if(! $media) {
            return null;
        }

        if (is_a($media, 'Spatie\MediaLibrary\MediaCollections\Models\Media')) {
            $entry->registerMediaConversions($media);

            $entry->{$this->fieldName} = $this->getMediaIdentifier($media, $entry);
        }else{
            $entry->{$this->fieldName} = $media->map(function($item) use ($entry) {
                return $this->getMediaIdentifier($item, $entry);
            })->toArray();
        }
        return $entry;
    }

    protected function addMediaFile($entry, $file, $order = null)
    {
        $fileAdder = is_a($file, UploadedFile::class, true) ? $entry->addMedia($file) : $entry->addMediaFromBase64($file);

        $fileAdder = $fileAdder->usingName($this->mediaName)
                                ->usingFileName($this->getFileName($file).'.'.$this->getExtensionFromFile($file));

        if ($order !== null) {
            $fileAdder->setOrder($order);
        }

        $constrainedMedia = new ConstrainedFileAdder(null);
        $constrainedMedia->setFileAdder($fileAdder);

        if ($this->savingEventCallback && is_callable($this->savingEventCallback)) {
            $constrainedMedia = call_user_func_array($this->savingEventCallback, [$constrainedMedia, $this]);
        }

        $constrainedMedia->getFileAdder()->toMediaCollection($this->collection, $this->disk);
    }

    protected function getMediaIdentifier($media, $entry = null)
    {
        $path = PathGeneratorFactory::create($media);

        if($entry && !empty($entry->mediaConversions)) {
            $conversion = array_filter($entry->mediaConversions, function($item) use ($media) {
                return $item->getName() === $this->getConversionToDisplay($media);
            })[0] ?? [];

            if (! $conversion) {
                return $path->getPath($media).$media->file_name;
            }
           
            return $path->getPathForConversions($media).$conversion->getConversionFile($media);
        }

        return $path->getPath($media).$media->file_name;
    }

    private function getConversionToDisplay($item) {
        foreach($this->displayConversions as $displayConversion)
        {
            if($item->hasGeneratedConversion($displayConversion)) {
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
