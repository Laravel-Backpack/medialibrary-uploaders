<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class MultipleFiles extends Uploader
{
    public static function for(array $field, $configuration): self
    {
        return (new static($field, $configuration))->multiple();
    }

    public function save(Model $entry, $value = null)
    {
        return $this->isRepeatable && ! $this->isRelationship ? $this->saveRepeatableUploadMultiple($entry, $value) : $this->saveUploadMultiple($entry, $value);
    }

    private function saveUploadMultiple($entry, $value = null)
    {
        $filesToDelete = request()->get('clear_'.$this->fieldName);

        $value = $value ?? request()->file($this->fieldName);

        $previousFiles = $entry->getOriginal($this->fieldName) ?? [];

        if (! is_array($previousFiles) && is_string($previousFiles)) {
            $previousFiles = json_decode($previousFiles, true);
        }

        if ($filesToDelete) {
            foreach ($previousFiles as $previousFile) {
                if (in_array($previousFile, $filesToDelete)) {
                    Storage::disk($this->disk)->delete($previousFile);

                    $previousFiles = Arr::where($previousFiles, function ($value, $key) use ($previousFile) {
                        return $value != $previousFile;
                    });
                }
            }
        }

        foreach ($value ?? [] as $file) {
            if ($file && is_file($file)) {
                $fileName = $this->getFileName($file).'.'.$this->getExtensionFromFile($file);

                $file->storeAs($this->path, $fileName, $this->disk);

                $previousFiles[] = $this->path.$fileName;
            }
        }

        return isset($entry->getCasts()[$this->fieldName]) ? $previousFiles : json_encode($previousFiles);
    }

    private function saveRepeatableUploadMultiple($entry, $value)
    {
        $previousFiles = $this->getPreviousRepeatableValues($entry);

        $fileOrder = $this->getFromRequestAsArray('_order_', ',');

        $files = $value ?? CrudPanelFacade::getRequest()->file($this->parentField) ?? [];

        foreach ($files as $row => $rowValue) {
            foreach ($rowValue[$this->fieldName] ?? [] as $file) {
                if ($file && is_file($file)) {
                    $fileName = $this->getFileName($file).'.'.$this->getExtensionFromFile($file);

                    $file->storeAs($this->path, $fileName, $this->disk);
                    $fileOrder[$row][] = $this->path.$fileName;
                }
            }
        }

        foreach ($previousFiles as $previousRow => $files) {
            foreach ($files as $key => $file) {
                $key = array_search($file, $fileOrder, true);
                if ($key === false) {
                    Storage::disk($this->disk)->delete($file);
                }
            }
        }

        return $fileOrder;
    }

    private function getFromRequestAsArray(string $key, $delimiter = null): array
    {
        $items = CrudPanelFacade::getRequest()->input($key.$this->parentField) ?? [];

        array_walk($items, function (&$key, $value) use ($delimiter) {
            $requestValue = $key[$this->fieldName] ?? null;
            if (is_string($requestValue) && $delimiter) {
                $key = explode($delimiter, $requestValue);
            } else {
                $key = $requestValue;
            }
        });

        return $items;
    }
}
