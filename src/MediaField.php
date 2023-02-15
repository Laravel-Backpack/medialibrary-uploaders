<?php

namespace Backpack\MediaLibraryUploads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class MediaField
{
    public $isRepeatable = false;

    public $fieldName;

    public $parentField;

    public $fileName = null;

    public $mediaName;

    public $collection = 'default';

    public $disk;

    public $savingEventCallback = null;

    public $getCallback = null;

    public $isMultiple = false;

    public $model;

    public function __construct(array $field)
    {
        $this->fieldName = $field['mediaName'];
        $this->model = $field['mediaModel'];
    }

    abstract public function save(Model $entry, $value = null);

    abstract public function getForDisplay(Model $entry);

    /**
     * Overwrite the default backpack definition with developer preferences
     *
     * @param array $definition
     * @return self
     */
    public function definition($definition)
    {
        $definition = (array) $definition;

        if (isset($definition['collection'])) {
            $modelDefinition = $this->modelInstance()->getRegisteredMediaCollections()
                                    ->reject(function ($item) use ($definition) {
                                        $item->name !== $definition['collection'] ?? '';
                                    })
                                    ->first();
        }
        
        $this->disk = $modelDefinition?->diskName ?? $definition['disk'] ?? config('media-library.disk_name');
        $this->collection = $definition['collection'] ?? 'default';
        $this->mediaName = $definition['name'] ?? $this->fieldName;
        $this->savingEventCallback = $definition['saving'] ?? null;

        return $this;
    }

    public function get(Model $entry)
    {
        $callback = $this;

        if ($this->getCallback) {
            $callback = call_user_func($this->getCallback, $this);
        }

        if ($this->isRepeatable || $this->isMultiple) {
            return $entry->getMedia($callback->collection, function ($media) use ($callback) {
                return $media->name === $callback->mediaName;
            });
        }

        return $entry->getFirstMedia($this->collection, function ($media) {
            return $media->name === $this->mediaName;
        });
    }

    public static function name(array $field): self
    {
        return new static($field);
    }

    public function multiple()
    {
        $this->isMultiple = true;

        return $this;
    }

    public function repeats(string $parentField)
    {
        $this->isRepeatable = true;

        $this->parentField = $this->collection = $parentField;

        return $this;
    }

    public function getRepeatableItemsAsArray($entry)
    {
        return $this->get($entry)->transform(function ($item) {
            return [$this->fieldName => $item->getUrl(), 'order_column' => $item->order_column];
        })->sortBy('order_column')->keyBy('order_column')->toArray();
    }

    private function getFileName($file)
    {
        if (is_file($file)) {
            return $this->fileName ?? Str::beforeLast($file->getClientOriginalName(), '.');
        }

        return $this->fileName ?? Str::random(40);
    }

    public function addMediaFileFromBase64($entry, $file, $extension)
    {
        $entry = $entry
            ->addMediaFromBase64($file);

        return $this->mediaWithCustomNames($entry, $file, $extension);
    }

    private function mediaWithCustomNames($entry, $file, $extension)
    {
        return $entry->usingName($this->mediaName)
            ->usingFileName($this->getFileName($file).'.'.$extension);
    }

    public function addMediaFile($entry, $file)
    {
        $entry = $entry
            ->addMedia($file);

        return $this->mediaWithCustomNames($entry, $file, $file->extension());
    }

    public function getCallback(callable|null $callback)
    {
        $this->getCallback = $callback;

        return $this;
    }

    private function modelInstance()
    {
        return new $this->model;
    }
}
