<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\MediaLibraryUploads\Interfaces\RepeatableUploaderInterface;
use Backpack\MediaLibraryUploads\Interfaces\UploaderInterface;
use Illuminate\Database\Eloquent\Model;

abstract class RepeatableUploader implements RepeatableUploaderInterface
{
    public $fieldName;

    public $repeatableUploads;

    public $isRelationship;

    abstract public function saveRepeatableCallback($entry, $upload, $values);

    abstract public function saveRelationshipCallback($entry, $upload, $value, $row);

    abstract public function retrieveFromRelationshipCallback($entry, $upload);

    abstract public function retrieveFromRepeatableCallback($entry);

    public function __construct(array $field)
    {
        $this->fieldName = $field['name'];
        $this->isRelationship = isset($field['relation_type']) && $field['entity'] !== false;
    }

    public static function for(array $field): self
    {
        return new static($field);
    }

    public function uploads(...$uploads): self
    {
        foreach ($uploads as $upload) {
            if (! is_a($upload, UploaderInterface::class)) {
                throw new \Exception('Uploads must be an instance of UploaderInterface.');
            }
            $this->repeatableUploads[] = $upload->repeats($this->fieldName)->relationship($this->isRelationship);
        }

        $this->setupSubfieldsUploadSettings();

        return $this;
    }

    private function save(Model $entry, $value = null)
    {
        return $this->isRelationship ? $this->saveRelationship($entry, $value) : $this->saveRepeatable($entry, $value);
    }

    private function saveRepeatable($entry)
    {
        $values = collect(request()->get($this->fieldName));
        foreach ($this->repeatableUploads as $upload) {
            $values = $this->saveRepeatableCallback($entry, $upload, $values);
        }

        return $values;
    }

    private function setupSubfieldsUploadSettings()
    {
        $crudField = CRUD::field($this->fieldName);

        $subfields = collect($crudField->getAttributes()['subfields']);
        $subfields = $subfields->map(function ($item) {
            if (isset($item['withMedia']) || isset($item['withUploads'])) {
                $uploader = array_filter($this->repeatableUploads, function ($item) {
                    return $item->fieldName !== $this->fieldName;
                })[0];
                $item['upload'] = true;
                $item['disk'] = $uploader->disk;
                $item['prefix'] = $uploader->path;
                if ($uploader->temporary) {
                    $item['temporary'] = $uploader->temporary;
                    $item['expiration'] = $uploader->expiration;
                }
            }

            return $item;
        })->toArray();

        $crudField->subfields($subfields);
    }

    private function saveRelationship($entry)
    {
        $values = collect(request()->get($this->fieldName));
        $files = collect(request()->file($this->fieldName));

        $values = $this->mergeValuesRecursive($values, $files);

        $modelCount = CRUD::get('model_count_'.$this->fieldName);

        $value = collect($values)->slice($modelCount, 1);

        foreach ($this->repeatableUploads as $upload) {
            $entry = $this->saveRelationshipCallback($entry, $upload, $value, $modelCount);
        }

        return $entry;
    }

    public function processFileUpload(Model $entry)
    {
        if (! $this->isRelationship) {
            $entry->{$this->fieldName} = json_encode($this->save($entry));
        } else {
            $entry = $this->save($entry);
        }

        return $entry;
    }

    public function retrieveUploadedFile(Model $entry)
    {
        return $this->isRelationship ? $this->retrieveFromRelationship($entry) : $this->retrieveFromRepeatable($entry);
    }

    private function retrieveFromRepeatable($entry)
    {
        $entry = $this->retrieveFromRepeatableCallback($entry);

        return $entry;
    }

    private function retrieveFromRelationship($entry)
    {
        foreach ($this->repeatableUploads as $upload) {
            $this->retrieveFromRelationshipCallback($entry, $upload);
        }

        return $entry;
    }

    protected function mergeValuesRecursive($array1, $array2)
    {
        $merged = $array1;
        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->mergeValuesRecursive($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
