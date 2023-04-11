<?php

namespace Backpack\MediaLibraryUploads\Uploaders\MediaLibrary;

use Backpack\MediaLibraryUploads\Interfaces\UploaderInterface;
use Backpack\MediaLibraryUploads\Uploaders\RepeatableUploader;
use Illuminate\Database\Eloquent\Model;

class MediaRepeatable extends RepeatableUploader
{
    public static function for(array $crudObject)
    {
        if (isset($crudObject['relation_type']) && $crudObject['entity'] !== false) {
            return new MediaRepeatableRelationship($crudObject);
        }

        return new static($crudObject);
    }

    protected function performSave(Model $entry, UploaderInterface $upload, $values, $row = null)
    {
        $upload->save($entry, $values->pluck($upload->getName())->toArray());

        $values->transform(function ($item) use ($upload) {
            unset($item[$upload->getName()]);

            return $item;
        });

        return $values;
    }

    protected function retrieveFiles($entry, $upload)
    {
        $values = $entry->{$this->name} ?? [];

        if (! is_array($values)) {
            $values = json_decode($values, true);
        }

        $uploadValues = $upload->getPreviousRepeatableValues($entry);
        $values = $this->mergeValuesRecursive($values, $uploadValues);

        $entry->{$this->name} = $values;

        return $entry;
    }
}
