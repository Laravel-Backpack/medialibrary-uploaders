<?php

namespace Backpack\MediaLibraryUploaders\Uploaders\Traits;

use Backpack\MediaLibraryUploaders\ConstrainedFileAdder;
use Illuminate\Database\Eloquent\Model;
use Exception;

trait AddMediaToModels
{
    protected function addMediaFile($entry, $file, $order = null)
    {
        $this->order = $order;

        $fileAdder = $this->initFileAdder($entry, $file);

        $fileAdder = $fileAdder->usingName($this->mediaName)
                                ->withCustomProperties($this->getCustomProperties())
                                ->usingFileName($this->getFileName($file));

        $constrainedMedia = new ConstrainedFileAdder();
        $constrainedMedia->setFileAdder($fileAdder);
        $constrainedMedia->setMediaUploader($this);

        if ($this->savingEventCallback && is_callable($this->savingEventCallback)) {
            $constrainedMedia = call_user_func_array($this->savingEventCallback, [$constrainedMedia, $this]);
        }

        if (! $constrainedMedia) {
            throw new Exception('Please return a valid class from `whenSaving` closure. Field: '.$this->getName());
        }

        $constrainedMedia->getFileAdder()->toMediaCollection($this->collection, $this->getDisk());
    }

    public function storeUploadedFiles(Model $entry): Model
    {
        if ($this->handleRepeatableFiles) {
            return $this->handleRepeatableFiles($entry);
        }

        $this->uploadFiles($entry);

        // make sure we remove the attribute from the model in case developer is using it in fillable
        // or using guarded in their models.
        $entry->offsetUnset($this->getName());
        // setting the raw attributes makes sure the `attributeCastCache` property is cleared, preventing
        // uploaded files from being re-added to the entry from the cache.
        $entry = $entry->setRawAttributes($entry->getAttributes());

        return $entry;
    }

    private function getModelInstance($crudObject): Model
    {
        return new ($crudObject['baseModel'] ?? get_class(app('crud')->getModel()));
    }
}
