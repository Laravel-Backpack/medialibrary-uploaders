<?php

namespace Backpack\MediaLibraryUploads\Uploaders\MediaLibrary;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class MediaSingleFile extends MediaUploader
{
    public function save(Model $entry, $value = null)
    {
        return $this->isRepeatable ? $this->saveRepeatableUpload($entry, $value) : $this->saveUpload($entry, $value);
    }

    private function saveRepeatableUpload($entry): void
    {
        $values = CRUD::getRequest()->file($this->parentField) ?? [];
        
        $filesToClear = $this->getFromRequestAsArray('_clear_');
        $orderedFiles = $this->getFromRequestAsArray('_order_');
        
        $previousFiles = array_column($this->getPreviousRepeatableMedia($entry), $this->name);

        foreach ($values as $row => $rowValue) {
            if (isset($rowValue[$this->name]) && is_file($rowValue[$this->name])) {
                $this->addMediaFile($entry, $rowValue[$this->name], $row);
            }
        }

        foreach ($previousFiles as $previousFile) {
            $previousFileIdentifier = $this->getMediaIdentifier($previousFile, $entry);
            if (in_array($previousFileIdentifier, $filesToClear)) {
                $previousFile->delete();
                continue;
            }

            if (in_array($previousFileIdentifier, $orderedFiles)) {
                $previousFile->setCustomProperty('repeatableRow', array_search($previousFileIdentifier, $orderedFiles));
                $previousFile->save();
            }else{
                $previousFile->delete();
            }
        }
    }

    private function getFromRequestAsArray(string $key): array
    {
        $items = CRUD::getRequest()->input($key.$this->parentField) ?? [];

        array_walk($items, function (&$key, $value) {
            $key = $key[$this->name] ?? null;
        });

        return $items;
    }

    private function saveUpload($entry, $value = null): void
    {
        $value = $value ?? CRUD::getRequest()->file($this->name);
       
        $previousFile = $this->get($entry);

        if ($previousFile && ($value && is_a($value, UploadedFile::class) || request()->has($this->name))) {
            $previousFile->delete();
        }

        if ($value && is_a($value, UploadedFile::class)) {
            $this->addMediaFile($entry, $value);
        }
    }
}