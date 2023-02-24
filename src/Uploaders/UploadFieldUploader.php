<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Backpack\MediaLibraryUploads\ConstrainedFileAdder;
use Illuminate\Database\Eloquent\Model;

class UploadFieldUploader extends Uploader
{
    public function save(Model $entry, $value = null)
    {
        return $this->isRepeatable ? $this->saveRepeatableUpload($entry, $value) : $this->saveUpload($entry, $value);
    }

    private function saveRepeatableUpload($entry): void
    {
        $values = CrudPanelFacade::getRequest()->file($this->parentField) ?? [];
        $filesToClear = $this->getFromRequestAsArray('_clear_');
        $orderedFiles = $this->getFromRequestAsArray('_order_');
        $previousFiles = $this->get($entry);

        foreach ($values as $row => $rowValue) {
            if (isset($rowValue[$this->fieldName]) && is_file($rowValue[$this->fieldName])) {
                $media = $this->addMediaFile($entry, $rowValue[$this->fieldName]);
                $media = $media->setOrder($row);

                /** @var \Spatie\MediaLibrary\MediaCollections\FileAdder $constrainedMedia */
                $constrainedMedia = new ConstrainedFileAdder(null);
                $constrainedMedia->setFileAdder($media);

                if ($this->savingEventCallback && is_callable($this->savingEventCallback)) {
                    $constrainedMedia = call_user_func_array($this->savingEventCallback, [$constrainedMedia, $this]);
                }

                $constrainedMedia->getFileAdder()->toMediaCollection($this->collection, $this->disk);
            }
        }

        foreach ($previousFiles as $previousFile) {
            if (in_array($previousFile->getUrl(), $filesToClear)) {
                $previousFile->delete();
            }

            if (in_array($previousFile->getUrl(), $orderedFiles)) {
                $previousFile->order_column = array_search($previousFile->getUrl(), $orderedFiles);
                $previousFile->save();
            }
        }
    }

    private function getFromRequestAsArray(string $key): array
    {
        $items = CrudPanelFacade::getRequest()->input($key.$this->parentField) ?? [];

        array_walk($items, function (&$key, $value) {
            $key = $key[$this->fieldName] ?? null;
        });

        return $items;
    }

    private function saveUpload($entry): void
    {
        $value = request()->file($this->fieldName);

        dd($entry);
        $previousFile = $this->get($entry);

        if ($previousFile && ($value && is_file($value) || request()->has($this->fieldName))) {
            $previousFile->delete();
        }

        if (is_file($value)) {
            $media = $this->addMediaFile($entry, $value);

            $constrainedMedia = new ConstrainedFileAdder(null);
            $constrainedMedia->setFileAdder($media);

            if ($this->savingEventCallback && is_callable($this->savingEventCallback)) {
                $constrainedMedia = call_user_func_array($this->savingEventCallback, [$constrainedMedia, $this]);
            }

            $constrainedMedia->getFileAdder()->toMediaCollection($this->collection, $this->disk);
        }
    }

    public function getForDisplay($entry): ?string
    {
        $media = $this->get($entry);

        return $media ? $media->getUrl() : null;
    }
}
