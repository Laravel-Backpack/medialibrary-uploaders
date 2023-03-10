<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SingleFile extends Uploader
{
    public function save(Model $entry, $value = null)
    {
        return $this->isRepeatable && ! $this->isRelationship ? $this->saveRepeatableFile($entry, $value) : $this->saveFile($entry, $value);
    }

    private function saveRepeatableFile($entry, $values)
    {
        $orderedFiles = $this->getFileOrderFromRequest();

        $previousFiles = $this->getPreviousRepeatableValues($entry);

        foreach ($values as $row => $file) {
            if ($file && is_file($file) && $file->isValid()) {
                $fileName = $this->getFileNameWithExtension($file);

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

    private function saveFile($entry, $value)
    {
        $value = $value ?? CrudPanelFacade::getRequest()->file($this->name);

        $previousFile = $entry->getOriginal($this->name);

        if ($value && is_file($value) && $value->isValid()) {
            if ($previousFile) {
                Storage::disk($this->disk)->delete($previousFile);
            }
            $fileName = $this->getFileNameWithExtension($value);

            $value->storeAs($this->path, $fileName, $this->disk);

            return $this->path.$fileName;
        }

        if (! $value && CrudPanelFacade::getRequest()->has($this->name) && $previousFile) {
            Storage::disk($this->disk)->delete($previousFile);

            return null;
        }

        return $previousFile;
    }
}
