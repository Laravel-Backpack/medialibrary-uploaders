<?php

namespace Backpack\MediaLibraryUploads\Uploaders\MediaLibrary;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\MediaLibraryUploads\Interfaces\RepeatableUploaderInterface;
use Backpack\MediaLibraryUploads\Interfaces\UploaderInterface;
use Illuminate\Database\Eloquent\Model;
use Backpack\MediaLibraryUploads\Uploaders\RepeatableRelationship;

class MediaRepeatableRelationship extends MediaRepeatable
{
    public function __construct(array $field) 
    {
        parent::__construct($field);
        $this->isRelationship = true;
    }

    public function save(Model $entry, $value = null)
    {
        $values = collect(request()->get($this->fieldName));
        $files = collect(request()->file($this->fieldName));

        $value = $this->mergeValuesRecursive($values, $files);

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

    public function retrieveFiles($entry, $upload) {
        
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
}
