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
        $values = collect(request()->get($this->name));
        $files = collect(request()->file($this->name));

        $value = $this->mergeValuesRecursive($values, $files);

        $modelCount = CRUD::get('model_count_'.$this->name);

        $value = collect($values)->slice($modelCount, 1);

        foreach ($this->repeatableUploads as $upload) {
            if (isset($value[$modelCount][$upload->name])) {
                $upload->save($entry, $value[$modelCount][$upload->name]);
            }
            $entry->offsetUnset($upload->name);
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
            $entry->{$upload->name} = $upload->getMediaIdentifier($media, $entry);
        } else {
            $entry->{$upload->name} = $media->map(function ($item) use ($entry, $upload) {
                return $upload->getMediaIdentifier($item, $entry);
            })->toArray();
        }

        return $entry;
    }
}
