<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\MediaLibraryUploads\Interfaces\RepeatableUploaderInterface;
use Backpack\MediaLibraryUploads\Interfaces\UploaderInterface;
use Illuminate\Database\Eloquent\Model;

class RepeatableUploader implements RepeatableUploaderInterface
{
    public $fieldName;

    public $repeatableUploads;

    public $isRelationship;

    public function __construct(array $field)
    public $crudObjectType;
    {
        $this->fieldName = $field['name'];
        $this->crudObjectType = $crudObject['crudObjectType'];
    }

    public static function for(array $field)
    {
        if(isset($field['relation_type']) && $field['entity'] !== false) {
            return new RepeatableRelationship($field);
        }
        return new static($field);
    }

    public function uploads(...$uploads): self
    {
        foreach ($uploads as $upload) {
            if (! is_a($upload, UploaderInterface::class)) {
                throw new \Exception('Uploads must be an instance of UploaderInterface.');
            }
            $this->repeatableUploads[] = $upload->repeats($this->fieldName)->relationship($this->isRelationship ?? false);
        }

        $this->setupSubfieldsUploadSettings();

        return $this;
    }

    public function save(Model $entry, $value = null)
    {
        $values = collect(request()->get($this->fieldName));
        $files = collect(request()->file($this->fieldName));

        $value = $this->mergeValuesRecursive($values, $files);

        foreach ($this->repeatableUploads as $upload) {
            $value = $this->performSave($entry, $upload, $value);
        }

        return $value;
    }

    protected function performSave($entry, $upload, $value, $row = null)
    {
        $uploadedValues = $upload->save($entry, $value->pluck($upload->fieldName)->toArray());

        $value = $value->map(function ($item, $key) use ($upload, $uploadedValues) {
            $item[$upload->fieldName] = $uploadedValues[$key] ?? null;

            return $item;
        });

        return $value;
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
        foreach($this->repeatableUploads as $upload) {
            $entry = $this->retrieveFiles($entry, $upload);
        }
        return $entry;
    }

    protected function retrieveFiles($entry, $upload)
    {
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
