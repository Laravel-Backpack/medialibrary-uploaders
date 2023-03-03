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

    public $savingEventCallback = null;

    public $isMultiple = false;

    public $eventsModel;

    public $path;

    public $temporary;

    public $expiration;

    public $isRelationship;

    public function __construct(array $field, $definition)
    {
        $this->fieldName = $field['name'];
        $this->disk = $definition['disk'] ?? $field['disk'] ?? config('backpack.base.root_disk_name');
        $this->savingEventCallback = $definition['whenSaving'] ?? null;
        $this->temporary = $definition['temporary'] ?? false;
        $this->expiration = $definition['expiration'] ?? 1;
        $this->eventsModel = $field['eventsModel'];
        $this->path = $definition['path'] ?? '';
        $this->isRelationship = false;

        if (! empty($this->path) && ! Str::endsWith($this->path, '/')) {
            $this->path = $this->path.'/';
        }

        $this->setupUploadConfigsInField($field);
    }

    abstract public function save(Model $entry, $values = null);

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

    protected function relationship(bool $isRelationship)
    {
        if ($isRelationship) {
            $this->isRepeatable = false;
        }
        $this->isRelationship = $isRelationship;

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
        $crudField = CRUD::field($field['parentFieldName'] ?? $field['name']);

        if (isset($field['parentFieldName'])) {
            $this->isRepeatable = true;
            $this->parentField = $field['parentFieldName'];

            $subfields = $crudField->getAttributes()['subfields'];

            $modifiedSubfields = [];
            foreach ($subfields as $subfield) {
                if ($subfield['name'] === $this->fieldName) {
                    $subfield['upload'] = true;
                    $subfield['disk'] = $field['disk'] ?? $this->disk;
                    $subfield['prefix'] = $field['prefix'] ?? $field['path'] ?? $this->path;
                    $modifiedSubfields[] = $subfield;
                    continue;
                }
                $modifiedSubfields[] = $subfield;
            }
            $crudField->subfields($modifiedSubfields);
        } else {
            $crudField->upload(true)->disk($field['disk'] ?? $this->disk)->prefix($field['prefix'] ?? $field['path'] ?? $this->path);
        }
    }
}
