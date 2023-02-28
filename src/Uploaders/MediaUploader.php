<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\MediaLibraryUploads\ConstrainedFileAdder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;

abstract class MediaUploader extends Uploader
{
    public $mediaName;

    public $collection;

    public $eventsModel;

    public $displayConversions;

    public function __construct(array $field, array $configuration)
    {
        parent::__construct($field, $configuration);

        if (isset($configuration['collection'])) {
            $modelDefinition = $this->modelInstance()->getRegisteredMediaCollections()
                                    ->reject(function ($item) use ($configuration) {
                                        $item->name !== $configuration['collection'] ?? '';
                                    })
                                    ->first();
        }

        $this->displayConversions = $configuration['displayConversions'] ?? [];
        $this->displayConversions = (array)$this->displayConversions;

        $this->eventsModel = $field['eventsModel'];
        $this->disk = $modelDefinition?->diskName ?? $configuration['disk'] ?? config('media-library.disk_name');
        $this->collection = $configuration['collection'] ?? 'default';
        $this->mediaName = $configuration['name'] ?? $this->fieldName;
        $this->isMultiple = $configuration['single'] ?? false;
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
        $crudField = CrudPanelFacade::field($this->fieldName)->disk($this->disk)->prefix($this->path);

        if ($this->temporary) {
            $crudField->temporary($this->temporary)->expiration($this->expiration);
        }

        $entry->{$this->fieldName} = $this->getForDisplay($entry);

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

    public function getForDisplay(Model $entry)
    {
        $item = $this->get($entry);

        return $item ? $this->getMediaIdentifier($item) : null;
    }

    protected function getMediaIdentifier($item)
    {
        foreach($this->displayConversions as $conversion) {
            if($item->hasGeneratedConversion($conversion)) {
                $filename = Str::of($item->file_name);
                return $item->id.'/conversions/'.$filename->before('.').'-'.$conversion.'.'.$filename->after('.');
            }
        }

        return $item->id.'/'.$item->file_name;
    }
}
