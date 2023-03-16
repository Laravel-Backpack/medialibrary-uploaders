<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RepeatableRelationship extends RepeatableUploader
{
    public function __construct(array $field)
    {
        parent::__construct($field);
        $this->isRelationship = true;
    }

    public function save(Model $entry, $value = null)
    {
        $values = collect(request()->get($this->name));
        $files = collect(request()->file($this->name));

        $value = $this->mergeValuesRecursive($values, $files);

        $modelCount = CRUD::get('uploaded_'.$this->name.'_count');

        $value = collect($values)->slice($modelCount, 1);

        foreach ($this->repeatableUploads as $upload) {
            if (isset($value[$modelCount][$upload->name])) {
                $entry->{$upload->name} = $upload->save($entry, $value[$modelCount][$upload->name]);
            }
        }

        return $entry;
    }

    public function processFileUpload(Model $entry)
    {
        $entry = $this->save($entry);

        return $entry;
    }

    /**
     * The function called in the deleting event to delete the uploaded files upon entry deletion
     *
     * @param Model $entry
     * @return void
     */
    public function deleteUploadedFile(Model $entry)
    {
        foreach ($this->repeatableUploads as $upload) {
            $values = $entry->{$upload->name};

            if ($upload->isMultiple) {
                if (! isset($entry->getCasts()[$upload->name]) && is_string($values)) {
                    $values = json_decode($values, true);
                }
            } else {
                $values = (array) Str::after($values, $upload->path);
            }

            foreach ($values as $value) {
                Storage::disk($upload->disk)->delete($upload->path.$value);
            }
        }
    }
}
