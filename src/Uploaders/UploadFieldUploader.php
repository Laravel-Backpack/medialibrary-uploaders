<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class UploadFieldUploader extends Uploader
{
    public function save(Model $entry, $value = null)
    {
        return $this->isRepeatable ? $this->saveRepeatableUpload($entry, $value) : $this->saveUpload($entry, $value);
    }

    private function saveRepeatableUpload($entry)
    {
        $values = CrudPanelFacade::getRequest()->file($this->parentField) ?? [];

        $orderedFiles = $this->getFromRequestAsArray('_order_');

        $previousFiles = $this->getPreviousRepeatableValues($entry);

        foreach ($values as $row => $rowValue) {
            if (isset($rowValue[$this->fieldName]) && is_file($rowValue[$this->fieldName])) {
                $finalPath = $this->path.$this->getFileName($rowValue[$this->fieldName]).'.'.$this->getExtensionFromFile($rowValue[$this->fieldName]);

                Storage::disk($this->disk)->put($finalPath, $rowValue[$this->fieldName]);
                $orderedFiles[$row] = $finalPath;

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

    private function saveUpload($entry)
    {
        $value = CrudPanelFacade::getRequest()->file($this->fieldName);

        $previousFile = $entry->getOriginal($this->fieldName);

        if ($value && is_file($value) && $value->isValid()) {
            if ($previousFile) {
                Storage::disk($this->disk)->delete($previousFile);
            }

            $finalPath = $this->path.$this->getFileName($value).'.'.$this->getExtensionFromFile($value);

            Storage::disk($this->disk)->put($finalPath, $value);

            return $finalPath;
        }

        if (! $value && CrudPanelFacade::getRequest()->has($this->fieldName) && $previousFile) {
            Storage::disk($this->disk)->delete($previousFile);

            return null;
        }

        return $previousFile;
    }
}
