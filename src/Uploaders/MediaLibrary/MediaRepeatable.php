<?php

namespace Backpack\MediaLibraryUploads\Uploaders\MediaLibrary;

use Backpack\MediaLibraryUploads\Uploaders\RepeatableUploader;

class MediaRepeatable extends RepeatableUploader
{
    public static function for(array $field)
    {
        if(isset($field['relation_type']) && $field['entity'] !== false) {
            return new MediaRepeatableRelationship($field);
        }
        return new static($field);
    }

    protected function performSave($entry, $upload, $values, $row = null) {
        $upload->save($entry, $values->pluck($upload->fieldName)->toArray());

        $values->transform(function ($item) use ($upload) {
            unset($item[$upload->fieldName]);

            return $item;
        });

        return $values;
    }    

    protected function retrieveFiles($entry, $upload) {
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
}
