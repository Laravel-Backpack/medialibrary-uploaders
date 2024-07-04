<?php

namespace Backpack\MediaLibraryUploaders\Uploaders\Traits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

trait DeletesUploadedFiles
{
    protected function deleteFiles(Model|Pivot $entry)
    {
        if (! $this->shouldDeleteFiles()) {
            return;
        }

        $files = $entry->media->filter(function($query) {
            return $query->custom_properties['name'] == $this->getAttributeName() &&
                $query->custom_properties['repeatableContainerName'] == $this->getRepeatableContainerName() &&
                $query->custom_properties['repeatableRow'] == null;
        });

        $files->each->delete();
        
    }

    protected function deletePivotFiles(Model|Pivot $model)
    {
        if (! $this->shouldDeleteFiles()) {
            return;
        }

        if (! is_a($model, Pivot::class, true)) {
            $pivots = $model->{$this->getRepeatableContainerName()};
            foreach ($pivots as $pivot) {
                $pivot = $pivot->pivot->loadMissing('media');
                $this->deleteFiles($pivot);
            }

            return;
        }

        //this is a workaround for Laravel Pivot Models, because they don't bring the primary key when eager loading
        // https://github.com/laravel/framework/issues/31658
        $model->refresh();
        $model->loadMissing('media');

        $this->deleteFiles($model);
    }
}