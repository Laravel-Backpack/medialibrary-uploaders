<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class MediaUploadFieldUploader extends MediaUploader
{

    public function __construct(array $field, $configuration)
    {
        parent::__construct($field, $configuration ?? []);
        CRUD::field($this->fieldName)->upload(true);
    }

    public function save(Model $entry, $value = null)
    {
        return $this->isRepeatable ? $this->saveRepeatableUpload($entry, $value) : $this->saveUpload($entry, $value);
    }

    private function saveRepeatableUpload($entry): void
    {
        $values = CRUD::getRequest()->file($this->parentField) ?? [];
        
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
        $items = CRUD::getRequest()->input($key.$this->parentField) ?? [];

        array_walk($items, function (&$key, $value) {
            $key = $key[$this->fieldName] ?? null;
        });

        return $items;
    }

    private function saveUpload($entry): void
    {
        $value = CRUD::getRequest()->file($this->fieldName);
       
        $previousFile = $this->get($entry);

        if ($previousFile && ($value && is_a($value, UploadedFile::class) || request()->has($this->fieldName))) {
            $previousFile->delete();
        }

        if ($value && is_a($value, UploadedFile::class)) {
            $this->addMediaFile($entry, $value);
        }
    }
}
