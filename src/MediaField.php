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

    public $saveCallback = null;

    public $getCallback = null;

    public $isMultiple = false;

    public function __construct(string $fieldName)
    {
        $this->fieldName = $this->mediaName = $fieldName;
        $this->disk = config('media-library.disk_name');
    }

    abstract public function save(Model $entry, $value = null);

    abstract public function getForDisplay(Model $entry);

    public function get(Model $entry)
    {
        $callback = $this;
        
        if($this->getCallback) {
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

    public static function name(string $name): self
    {
        return new static($name);
    }

    public function collection(string $collection): self
    {
        $this->collection = $collection;

        return $this;
    }

    public function mediaName(string $mediaName): self
    {
        $this->mediaName = $mediaName;

        return $this;
    }

    public function disk(string $disk): self
    {
        $this->disk = $disk;

        return $this;
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
        if (is_callable($this->fileName)) {
            return call_user_func_array($this->fileName, [$file, $this]);
        }

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

    public function saveCallback(callable|null $callback)
    {
        $this->saveCallback = $callback;

        return $this;
    }

    public function getCallback(callable|null $callback)
    {
        $this->getCallback = $callback;

        return $this;
    }
}
