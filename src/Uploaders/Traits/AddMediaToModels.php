<?php

namespace Backpack\MediaLibraryUploaders\Uploaders\Traits;

use Backpack\MediaLibraryUploaders\ConstrainedFileAdder;
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
}
