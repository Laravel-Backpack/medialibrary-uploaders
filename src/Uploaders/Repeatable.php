<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

class Repeatable extends RepeatableUploader
{
    public function saveRepeatableCallback($entry, $upload, $values)
    {
        $uploadedValues = $upload->save($entry, $values->pluck($upload->fieldName)->toArray());

        $values = $values->map(function ($item, $key) use ($upload, $uploadedValues) {
            $item[$upload->fieldName] = $uploadedValues[$key] ?? null;

            return $item;
        });

        return $values;
    }

    public function saveRelationshipCallback($entry, $upload, $values, $row)
    {
        if (isset($values[$row][$upload->fieldName])) {
            $entry->{$upload->fieldName} = $upload->save($entry, $values[$row][$upload->fieldName]);
        }

        return $entry;
    }

    public function retrieveFromRelationshipCallback($entry, $upload)
    {
        return $entry;
    }

    public function retrieveFromRepeatableCallback($entry)
    {
        return $entry;
    }
}
