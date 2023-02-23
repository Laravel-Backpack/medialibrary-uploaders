<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Illuminate\Database\Eloquent\Model;

class RepeatableUploads extends Uploader
{
    public $repeatableUploads;

    public function __construct(array $field)
    {
        $this->fieldName = $field['name'];
    }

    public function get($entry)
    {
        return $this->getForDisplay($entry);
    }

    public function uploads(...$uploads)
    {
        foreach ($uploads as $upload) {
            if (! is_a($upload, \Backpack\MediaLibraryUploads\Uploaders\Uploader::class)) {
                throw new \Exception('Uploads must be an instance of Uploader class.');
            }
            $this->repeatableUploads[] = $upload->repeats($this->fieldName);
        }

        return $this;
    }

    public function save(Model $entry, $value = null)
    {
        $values = collect(request()->get(self::$fieldName));
        foreach (self::$repeatableUploads as $upload) {
            $upload->save($entry, $values->pluck($upload->fieldName)->toArray());

            $values->transform(function ($item) use ($upload) {
                unset($item[$upload->fieldName]);

                return $item;
            });
        }

        return $values;
    }

    public function getForDisplay($entry)
    {
        $values = $entry->{self::$fieldName} ?? [];

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
