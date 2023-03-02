<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\MediaLibraryUploads\Interfaces\RepeatableUploaderInterface;
use Backpack\MediaLibraryUploads\Interfaces\UploaderInterface;
use Illuminate\Database\Eloquent\Model;

class MediaRepeatableUploads extends MediaUploader implements RepeatableUploaderInterface
{
    public $repeatableUploads;

    public function __construct($field)
    {
        $this->fieldName = $field['name'];
    }

    public function save(Model $entry, $value = null)
    {
        $values = collect(request()->get($this->fieldName));
        foreach ($this->repeatableUploads as $upload) {
            $upload->save($entry, $values->pluck($upload->fieldName)->toArray());

            $values->transform(function ($item) use ($upload) {
                unset($item[$upload->fieldName]);

                return $item;
            });
        }

        return $values;
    }

    public static function for(array $field, $definition): self
    {
        return new static($field, $definition);
    }

    public function uploads(...$uploads): self
    {
        foreach ($uploads as $upload) {
            if (! is_a($upload, UploaderInterface::class)) {
                throw new \Exception('Uploads must be an instance of UploaderInterface.');
            }
            $this->repeatableUploads[] = $upload->repeats($this->fieldName);
        }
        return $this;
    }

    public function retrieveUploadedFile(Model $entry)
    {
        $crudField = CRUD::field($this->fieldName);

        $subfields = collect($crudField->getAttributes()['subfields']);
        $subfields = $subfields->map(function ($item) {
            if (isset($item['withMedia']) || isset($item['withUploads'])) {
                $uploader = array_filter($this->repeatableUploads, function ($item) {
                    return $item->fieldName !== $this->fieldName;
                })[0];

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

        $values = $entry->{$this->fieldName} ?? [];

        if (! is_array($values)) {
            $values = json_decode($values, true);
        }

        foreach ($this->repeatableUploads as $upload) {
            $uploadValues = $upload->getPreviousRepeatableValues($entry);
            $values = $this->mergeValuesRecursive($values, $uploadValues);
        }
        
        $entry->{$this->fieldName} = $values;

        return $entry;
    }

    private function mergeValuesRecursive($array1, $array2)
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
