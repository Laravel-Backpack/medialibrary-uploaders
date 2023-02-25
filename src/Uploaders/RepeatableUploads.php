<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Backpack\MediaLibraryUploads\Interfaces\RepeatableUploaderInterface;
use Illuminate\Database\Eloquent\Model;

class RepeatableUploads extends Uploader implements RepeatableUploaderInterface
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

    public function uploads(...$uploads): self
    {
        foreach ($uploads as $upload) {
            if (! is_a($upload, \Backpack\MediaLibraryUploads\Interfaces\UploaderInterface::class)) {
                throw new \Exception('Uploads must be an instance of Uploader class.');
            }
            $this->repeatableUploads[] = $upload->repeats($this->fieldName);
        }

        return $this;
    }

    public function save(Model $entry, $value = null)
    {
        $values = collect(request()->get($this->fieldName));
        foreach ($this->repeatableUploads as $upload) {
            $uploadedValues = $upload->save($entry, $values->pluck($upload->fieldName)->toArray());

            $values = $values->map(function ($item, $key) use ($upload, $uploadedValues) {
                $item[$upload->fieldName] = $uploadedValues[$key] ?? null;

                return $item;
            });
        }

        return $values;
    }

    public function retrieveUploadedFile(Model $entry)
    {
        $crudField = CrudPanelFacade::field($this->fieldName);

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
        });

        $crudField->subfields($subfields->toArray());

        return $entry;
    }
}
