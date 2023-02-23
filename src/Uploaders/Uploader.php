<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\MediaLibraryUploads\Interfaces\UploaderInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class Uploader implements UploaderInterface
{
    public $isRepeatable = false;

    public $fieldName;

    public $parentField;

    public $fileName = null;

    public $disk;

    public $savingEventCallback = null;

    public $isMultiple = false;

    public $eventsModel;

    public function __construct(array $field, $definition)
    {
        $this->fieldName = $field['name'];
        $this->disk = $definition['disk'] ?? config('backpack.base.root_disk_name');
        $this->savingEventCallback = $definition['whenSaving'] ?? null;
    }

    abstract public function save(Model $entry, $value = null);

    abstract public function getForDisplay(Model $entry);

    public function getSavingEvent(Model $entry)
    {
        if (is_a($this, \Backpack\MediaLibraryUploads\Uploaders\RepeatableUploads::class)) {
            $entry->{$this->fieldName} = json_encode($this->save($entry));
        } else {
            $this->save($entry);
            $entry->offsetUnset($this->fieldName);
        }
    }

    public function getRetrievedEvent(Model $entry)
    {
        $entry->{$this->fieldName} = $this->getForDisplay($entry);
    }

    public static function for(array $field, $definition): self
    {
        return new static($field, $definition);
    }

    protected function multiple()
    {
        $this->isMultiple = true;

        return $this;
    }

    protected function repeats(string $parentField)
    {
        $this->isRepeatable = true;

        $this->parentField = $parentField;

        return $this;
    }

    protected function getFileName($file)
    {
        if (is_file($file)) {
            return Str::of($this->fileName ?? Str::beforeLast($file->getClientOriginalName(), '.'))->slug();
        }

        return Str::of($this->fileName ?? Str::random(40))->slug();
    }

    protected function modelInstance()
    {
        return new $this->eventsModel;
    }
}
