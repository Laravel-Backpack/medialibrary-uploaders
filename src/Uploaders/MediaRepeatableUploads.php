<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Illuminate\Database\Eloquent\Model;
use Backpack\MediaLibraryUploads\Interfaces\UploaderInterface;
use Backpack\MediaLibraryUploads\Interfaces\RepeatableUploaderInterface;

class MediaRepeatableUploads extends MediaUploader implements RepeatableUploaderInterface
{
    public $repeatableUploads;

    public function __construct($field) {
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

    public function uploads(...$uploads)
    {
        foreach ($uploads as $upload) {
            if (! is_a($upload, UploaderInterface::class)) {
                throw new \Exception('Uploads must be an instance of UploaderInterface.');
            }
            $this->repeatableUploads[] = $upload->repeats($this->fieldName);
        }

        return $this;
    }

    public function getForDisplay(Model $entry)
    {
        $values = $entry->{$this->fieldName} ?? [];

        if (! is_array($values)) {
            $values = json_decode($values, true);
        }

        foreach ($this->repeatableUploads as $upload) {
            $uploadValues = $upload->getRepeatableItemsAsArray($entry);
            $values = array_merge_recursive_distinct($values, $uploadValues);
        }

        return $values;
    }
}
