<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SingleFile extends Uploader
{
    public function save(Model $entry, $value = null)
    {
        return $this->isRepeatable && ! $this->isRelationship ? $this->saveRepeatableUpload($entry, $value) : $this->saveUpload($entry, $value);
    }

    private function saveRepeatableUpload($entry, $value)
    {
        $values = $value ?? CrudPanelFacade::getRequest()->file($this->parentField) ?? [];

        $orderedFiles = $this->getFromRequestAsArray('_order_');

        $previousFiles = $this->getPreviousRepeatableValues($entry);

        foreach ($values as $row => $rowValue) {
            $file = $rowValue[$this->fieldName] ?? null;
            if ($file && is_file($file) && $file->isValid()) {
                $fileName = $this->getFileName($file).'.'.$this->getExtensionFromFile($file);

                $file->storeAs($this->path, $fileName, $this->disk);
                $orderedFiles[$row] = $this->path.$fileName;

                continue;
            }
        }

        foreach ($previousFiles as $row => $file) {
            if ($file && ! isset($orderedFiles[$row])) {
                $orderedFiles[$row] = null;
                Storage::disk($this->disk)->delete($file);
            }
        }

        return $orderedFiles;
    }

    private function getFromRequestAsArray(string $key): array
    {
        $items = CrudPanelFacade::getRequest()->input($key.$this->parentField) ?? [];

        array_walk($items, function (&$key, $value) {
            $key = $key[$this->fieldName] ?? null;
        });

        return $items;
    }

    private function saveUpload($entry, $value)
    {
        $value = $value ?? CrudPanelFacade::getRequest()->file($this->fieldName);

        $previousFile = $entry->getOriginal($this->fieldName);

        if ($value && is_file($value) && $value->isValid()) {
            if ($previousFile) {
                Storage::disk($this->disk)->delete($previousFile);
            }
            $fileName = $this->getFileName($value).'.'.$this->getExtensionFromFile($value);

            $value->storeAs($this->path, $fileName, $this->disk);

            return $this->path.$fileName;
        }

        if (! $value && CrudPanelFacade::getRequest()->has($this->fieldName) && $previousFile) {
            Storage::disk($this->disk)->delete($previousFile);

            return null;
        }

        return $previousFile;
    }
}
