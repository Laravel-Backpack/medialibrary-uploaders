<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
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

    public $isMultiple = false;

    public $eventsModel;

    public $path;

    public $temporary;

    public $expiration;

    public $isRelationship;

    public function __construct(array $field, $configuration)
    {
        $this->fieldName = $field['name'];
        $this->disk = $configuration['disk'] ?? $field['disk'] ?? 'public';
        $this->temporary = $configuration['temporary'] ?? false;
        $this->expiration = $configuration['expiration'] ?? 1;
        $this->eventsModel = $field['eventsModel'];
        $this->path = $configuration['path'] ?? $field['prefix'] ?? '';
        $this->path = empty($this->path) ? $this->path : Str::of($this->path)->finish('/');
    }

    abstract public function save(Model $entry, $values = null);

    public function processFileUpload(Model $entry)
    {
        $entry->{$this->fieldName} = $this->save($entry);

        return $entry;
    }

    public function retrieveUploadedFile(Model $entry)
    {
        $this->setupUploadConfigsInField(CRUD::field($this->fieldName));

        $value = $entry->{$this->fieldName};

        if ($this->isMultiple && ! isset($entry->getCasts()[$this->fieldName]) && is_string($value)) {
            $entry->{$this->fieldName} = json_decode($value, true);
        } else {
            $entry->{$this->fieldName} = Str::after($value, $this->path);
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

    public function relationship(bool $isRelationship)
    {
        if ($isRelationship) {
            $this->isRepeatable = false;
        }
        $this->isRelationship = $isRelationship;

        return $this;
    }

    public function repeats(string $parentField)
    {
        $this->isRepeatable = true;

        $this->parentField = $parentField;

        return $this;
    }

    protected function getFileName($file)
    {
        if (is_file($file)) {
            return Str::of($this->fileName ?? Str::beforeLast($file->getClientOriginalName(), '.'))->slug()->append('-'.Str::random(4));
        }

        return Str::of($this->fileName ?? Str::random(40))->slug();
    }

    protected function modelInstance()
    {
        return new $this->eventsModel;
    }

    protected function getPreviousRepeatableValues(Model $entry)
    {
        $previousValues = json_decode($entry->getOriginal($this->parentField), true);
        if (! empty($previousValues)) {
            $previousValues = array_column($previousValues, $this->fieldName);
        }

        return $previousValues ?? [];
    }

    protected function getExtensionFromFile($file)
    {
        return is_a($file, UploadedFile::class, true) ? $file->extension() : Str::after(mime_content_type($file), '/');
    }

    private function setupUploadConfigsInField($field)
    {
        $attributes = $field->getAttributes();
        $field->upload(true)
            ->disk($attributes['disk'] ?? $this->disk)
            ->prefix($attributes['prefix'] ?? $this->path);
    }
}
