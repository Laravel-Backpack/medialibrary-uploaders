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

    public $name;

    public $parentField;

    public $fileName = null;

    public $disk;

    public $isMultiple = false;

    public $eventsModel;

    public $path;

    public $temporary;

    public $expiration;

    public $isRelationship;

    public $crudObjectType;

    public function __construct(array $crudObject, $configuration)
    {
        $this->name = $crudObject['name'];
        $this->disk = $configuration['disk'] ?? $crudObject['disk'] ?? 'public';
        $this->temporary = $configuration['temporary'] ?? false;
        $this->expiration = $configuration['expiration'] ?? 1;
        $this->eventsModel = $crudObject['eventsModel'];
        $this->path = $configuration['path'] ?? $crudObject['prefix'] ?? '';
        $this->path = empty($this->path) ? $this->path : Str::of($this->path)->finish('/');
        $this->crudObjectType = $crudObject['crudObjectType'];
    }

    abstract public function save(Model $entry, $values = null);

    public function processFileUpload(Model $entry)
    {
        $entry->{$this->name} = $this->save($entry);

        return $entry;
    }

    public function retrieveUploadedFile(Model $entry)
    {
        $this->setupUploadConfigsInCrudObject(CRUD::{$this->crudObjectType}($this->name));

        $value = $entry->{$this->name};

        if ($this->isMultiple && ! isset($entry->getCasts()[$this->name]) && is_string($value)) {
            $entry->{$this->name} = json_decode($value, true);
        } else {
            $entry->{$this->name} = Str::after($value, $this->path);
        }

        return $entry;
    }

    public static function for(array $crudObject, $definition)
    {
        return new static($crudObject, $definition);
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

    protected function getFileOrderFromRequest()
    {
        $items = CRUD::getRequest()->input('_order_'.$this->parentField) ?? [];

        array_walk($items, function (&$key, $value) {
            $requestValue = $key[$this->name] ?? null;
            $key = $this->isMultiple ? (is_string($requestValue) ? explode(',', $requestValue) : $requestValue) : $requestValue;
        });

        return $items;
    }

    protected function modelInstance()
    {
        return new $this->eventsModel;
    }

    protected function getPreviousRepeatableValues(Model $entry)
    {
        $previousValues = json_decode($entry->getOriginal($this->parentField), true);
        if (! empty($previousValues)) {
            $previousValues = array_column($previousValues, $this->name);
        }

        return $previousValues ?? [];
    }

    protected function getExtensionFromFile($file)
    {
        return is_a($file, UploadedFile::class, true) ? $file->extension() : Str::after(mime_content_type($file), '/');
    }

    protected function getFileName($file)
    {
        if (is_file($file)) {
            return Str::of($this->fileName ?? Str::of($file->getClientOriginalName())->beforeLast('.')->slug()->append('-'.Str::random(4)));
        }

        return Str::of($this->fileName ?? Str::random(40));
    }

    protected function getFileNameWithExtension($file)
    {
        if (is_file($file)) {
            return Str::of($this->fileName ?? Str::of($file->getClientOriginalName())->beforeLast('.')->slug()->append('-'.Str::random(4))).'.'.$this->getExtensionFromFile($file);
        }

        return Str::of($this->fileName ?? Str::random(40)).'.'.$this->getExtensionFromFile($file);
    }

    protected function setupUploadConfigsInCrudObject($crudObject)
    {
        $attributes = $crudObject->getAttributes();
        $crudObject->upload(true)
            ->disk($attributes['disk'] ?? $this->disk)
            ->prefix($attributes['prefix'] ?? $this->path);
    }
}
