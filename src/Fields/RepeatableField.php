<?php

namespace Backpack\MediaLibraryUploads\Fields;

class RepeatableField extends MediaField
{
    public $repeatableUploads;

    public $fieldName;

    public function __construct(array $field)
    {
        $this->fieldName = $field['name'];
    }

    public function get($entry)
    {
        return $this->getForDisplay($entry);
    }

    public static function name(array $field): self
    {
        return new static($field);
    }

    public function uploads(...$uploads)
    {
        foreach ($uploads as $upload) {
            if (! is_a($upload, \Backpack\MediaLibraryUploads\Fields\MediaField::class)) {
                dd($upload);
                throw new \Exception('Uploads must be an instance of MediaField');
            }
            $this->repeatableUploads[] = $upload->repeats($this->fieldName);
        }

        return $this;
    }

    public function save($entry, $value = null)
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

    public function getForDisplay($entry)
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
