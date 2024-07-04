<?php

namespace Backpack\MediaLibraryUploaders\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class MediaSingleFile extends MediaUploader
{
    public function uploadFiles(Model $entry, $value = null)
    {
        $value = $value ?? CRUD::getRequest()->file($this->getName());

        $previousFile = $this->getPreviousFiles($entry);

        if (is_a($previousFile, Collection::class, true)) {
            $previousFile = null;
        }

        if ($previousFile && ($value && is_a($value, UploadedFile::class) || request()->has($this->getName()))) {
            $previousFile->delete();
        }

        if ($value && is_a($value, UploadedFile::class)) {
            $this->addMediaFile($entry, $value);
        }
    }

    public function uploadRepeatableFiles($values, $previousFiles, $entry = null)
    {
        $values = CRUD::getRequest()->file($this->repeatableContainerName) ?? [];

        $filesToClear = CRUD::getRequest()->get('clear_'.$this->getRepeatableContainerName()) ?? [];
        $orderedFiles = $this->getFileOrderFromRequest();

        foreach ($values as $row => $rowValue) {
            if (isset($rowValue[$this->getName()]) && is_file($rowValue[$this->getName()])) {
                $this->addMediaFile($entry, $rowValue[$this->getName()], $row);
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
            } else {
                $previousFile->delete();
            }
        }
    }

    /**
     * Single file uploaders send no value when they are not dirty.
     */
    public function shouldKeepPreviousValueUnchanged(Model $entry, $entryValue): bool
    {
        return is_string($entryValue);
    }

    protected function hasDeletedFiles($entryValue): bool
    {
        return $entryValue === null;
    }

    protected function shouldUploadFiles($value): bool
    {
        return is_a($value, 'Illuminate\Http\UploadedFile', true);
    }
}
