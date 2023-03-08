<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\MediaLibraryUploads\Interfaces\RepeatableUploaderInterface;
use Backpack\MediaLibraryUploads\Interfaces\UploaderInterface;
use Illuminate\Database\Eloquent\Model;

class MediaRepeatable extends RepeatableUploader
{
    public function saveRepeatableCallback($entry, $upload, $values) {
        $upload->save($entry, $values->pluck($upload->fieldName)->toArray());

        $values->transform(function ($item) use ($upload) {
            unset($item[$upload->fieldName]);

            return $item;
        });

        return $values;
    }

    public function saveRelationshipCallback($entry, $upload, $value, $row) {
        if (isset($value[$row][$upload->fieldName])) {
            $upload->save($entry, $value[$row][$upload->fieldName]);
        }
        $entry->offsetUnset($upload->fieldName);

        return $entry;
    }

    public function retrieveFromRelationshipCallback($entry, $upload) {
        $media = $upload->get($entry);

        if (! $media) {
            return $entry;
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

        return $entry;
    }

    public function retrieveFromRepeatableCallback($entry) {
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
