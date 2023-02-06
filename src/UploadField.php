<?php

namespace Backpack\MediaLibraryUploads;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Database\Eloquent\Model;

class UploadField extends MediaField
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
//dd($rowValue[$this->fieldName]);
                $media = $this->addMediaFile($entry, $rowValue[$this->fieldName]);
                $media = $media->setOrder($row);

                if($this->saveCallback) {
                    $media = call_user_func_array($this->saveCallback, [$media, $this]);
                }
               
                if(is_a($media, \Spatie\MediaLibrary\MediaCollections\FileAdder::class)) {
                    $media->toMediaCollection($this->collection);
                }
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

        $previousFile = $this->get($entry);

        if ($previousFile && ($value && is_file($value) || request()->has($this->fieldName))) {
            $previousFile->delete();
        }
        //dd($value);
        if (is_file($value)) {
            $media = $this->addMediaFile($entry, $value);
            //dump($media);
            if($this->saveCallback) {  
                $media = call_user_func_array($this->saveCallback,[$media, $this]);
            }
            //dd($media);
            if(is_a($media, \Spatie\MediaLibrary\MediaCollections\FileAdder::class)) {
                $media->toMediaCollection($this->collection);
            }
        }
    }

    public function getForDisplay($entry): ?string
    {
        $media = $this->get($entry);

        return $media ? $media->getUrl() : null;
    }
}
