<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\MediaLibraryUploads\Interfaces\RepeatableUploaderInterface;
use Backpack\MediaLibraryUploads\Interfaces\UploaderInterface;
use Illuminate\Database\Eloquent\Model;

class RepeatableUploader implements RepeatableUploaderInterface
{
    public $name;

    public $repeatableUploads;

    public $isRelationship;

    public $crudObjectType;

    public function __construct(array $crudObject)
    {
        $this->name = $crudObject['name'];
        $this->crudObjectType = $crudObject['crudObjectType'];
    }

    public static function for(array $crudObject)
    {
        if (isset($crudObject['relation_type']) && $crudObject['entity'] !== false) {
            return new RepeatableRelationship($crudObject);
        }

        return new static($crudObject);
    }

    public function uploads(...$uploads): self
    {
        foreach ($uploads as $upload) {
            if (! is_a($upload, UploaderInterface::class)) {
                throw new \Exception('Uploads must be an instance of UploaderInterface.');
            }
            $this->repeatableUploads[] = $upload->repeats($this->name)->relationship($this->isRelationship ?? false);
        }

        $this->setupSubfieldsUploadSettings();

        return $this;
    }

    public function save(Model $entry, $value = null)
    {
        $values = collect(request()->get($this->name));
        $files = collect(request()->file($this->name));

        $value = $this->mergeValuesRecursive($values, $files);

        foreach ($this->repeatableUploads as $upload) {
            $value = $this->performSave($entry, $upload, $value);
        }

        return $value;
    }

    protected function performSave($entry, $upload, $value, $row = null)
    {
        $uploadedValues = $upload->save($entry, $value->pluck($upload->name)->toArray());

        $value = $value->map(function ($item, $key) use ($upload, $uploadedValues) {
            $item[$upload->name] = $uploadedValues[$key] ?? null;

            return $item;
        });

        return $value;
    }

    private function setupSubfieldsUploadSettings()
    {
        $crudField = CRUD::field($this->name);

        $subfields = collect($crudField->getAttributes()['subfields']);
        $subfields = $subfields->map(function ($item) {
            if (isset($item['withMedia']) || isset($item['withUploads'])) {
                $uploader = array_filter($this->repeatableUploads, function ($item) {
                    return $item->name !== $this->name;
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
            $entry->{$this->name} = json_encode($this->save($entry));
        } else {
            $entry = $this->save($entry);
        }

        return $entry;
    }

    public function retrieveUploadedFile(Model $entry)
    {
        foreach ($this->repeatableUploads as $upload) {
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
