<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Backpack\MediaLibraryUploads\Interfaces\UploaderInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
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

    public $path;

    public $temporary;

    public $expiration;

    public function __construct(array $field, $definition)
    {
        $this->fieldName = $field['name'];
        $this->disk = $definition['disk'] ?? config('backpack.base.root_disk_name');
        $this->savingEventCallback = $definition['whenSaving'] ?? null;
        $this->temporary = $definition['temporary'] ?? false;
        $this->expiration = $definition['expiration'] ?? 1;
        $this->path = $definition['path'] ?? '';
        if (! empty($this->path) && ! Str::endsWith($this->path, '/')) {
            $this->path = $this->path.'/';
        }
    }

    abstract public function save(Model $entry, $value = null);

    public function processFileUpload(Model $entry)
    {
        if (is_a($this, \Backpack\MediaLibraryUploads\Uploaders\RepeatableUploads::class)) {
            $entry->{$this->fieldName} = json_encode($this->save($entry));
        } else {
            $entry->{$this->fieldName} = $this->save($entry);
        }

        return $entry;
    }

    public function retrieveUploadedFile(Model $entry)
    {
        $crudField = CrudPanelFacade::field($this->fieldName)->disk($this->disk)->prefix($this->path);
        if ($this->temporary) {
            $crudField->temporary($this->temporary)->expiration($this->expiration);
        }

        return $entry;
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
            return Str::of($this->fileName ?? Str::beforeLast($file->getClientOriginalName(), '.'))->append('-'.Str::random(4))->slug();
        }

        return Str::of($this->fileName ?? Str::random(40))->slug();
    }

    protected function modelInstance()
    {
        return new $this->eventsModel;
    }

    protected function getExtensionFromFile($file)
    {
        return is_a($file, UploadedFile::class, true) ? $file->extension() : Str::after(mime_content_type($file), '/');
    }
}
