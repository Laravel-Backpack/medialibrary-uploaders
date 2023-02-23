<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class MediaUploadFieldUploader extends MediaUploader
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
                $this->addMediaFile($entry, $rowValue[$this->fieldName], $row);
            }
        }

        foreach ($previousFiles as $previousFile) {
            if (in_array($previousFile->getUrl(), $filesToClear)) {
                $previousFile->delete();
                continue;
            }

            if (in_array($previousFile->getUrl(), $orderedFiles)) {
                $previousFile->order_column = array_search($previousFile->getUrl(), $orderedFiles);
                $previousFile->save();
            }else{
                $previousFile->delete();
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

        $previousFile = $this->get($entry);

        if ($previousFile && ($value && is_a($value, UploadedFile::class) || request()->has($this->fieldName))) {
            $previousFile->delete();
        }

        if ($value && is_a($value, UploadedFile::class)) {
            $this->addMediaFile($entry, $value);
        }
    }

    public function getForDisplay($entry): ?string
    {
        $media = $this->get($entry);

        return $media ? $media->getUrl() : null;
    }
}
