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
        $this->isRelationship = isset($field['relation_type']) && $field['entity'] !== false;
    }

    public function save(Model $entry, $value = null)
    {
        return $this->isRelationship ? $this->saveRelationship($entry) : $this->saveRepeatable($entry);
    }

    private function saveRepeatable($entry)
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

    private function saveRelationship($entry)
    {
        $values = collect(request()->get($this->fieldName));
        $files = collect(request()->file($this->fieldName));

        $values = $this->mergeValuesRecursive($values, $files);

        $modelCount = CRUD::get('model_count_'.$this->fieldName);

        $value = collect($values)->slice($modelCount, 1);

        foreach ($this->repeatableUploads as $upload) {
            if (isset($value[$modelCount][$upload->fieldName])) {
                $upload->save($entry, $value[$modelCount][$upload->fieldName]);
            }
            $entry->offsetUnset($upload->fieldName);
        }

        return $entry;
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

            $this->repeatableUploads[] = $upload->repeats($this->fieldName)->relationship($this->isRelationship);
        }

        return $this;
    }

    public function retrieveUploadedFile(Model $entry)
    {
        return $this->isRelationship ? $this->retrieveFromRelationship($entry) : $this->retrieveFromRepeatable($entry);
    }

    private function retrieveFromRepeatable($entry)
    {
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

    private function retrieveFromRelationship($entry)
    {
        foreach ($this->repeatableUploads as $upload) {
            $media = $upload->get($entry);

            if (! $media) {
                continue;
            }

            if (empty($entry->mediaConversions)) {
                $entry->registerAllMediaConversions();
            }

            if (is_a($media, 'Spatie\MediaLibrary\MediaCollections\Models\Media')) {
                $entry->{$upload->fieldName} = $upload->getMediaIdentifier($media, $entry);
            } else {
                $entry->{$upload->fieldName} = $media->map(function ($item) use ($entry, $upload) {
                    return $upload->getMediaIdentifier($item, $entry);
                })->toArray();
            }
        }

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
