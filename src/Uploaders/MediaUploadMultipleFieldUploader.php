<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Model;

class MediaUploadMultipleFieldUploader extends MediaUploader
{
    public static function for(array $field, $configuration): self
    {
        return (new static($field, $configuration))->multiple();
    }

    public function save(Model $entry, $value = null)
    {
        return $this->isRepeatable ? $this->saveRepeatableUploadMultiple($entry, $value) : $this->saveUploadMultiple($entry, $value);
    }

    public function getForDisplay($entry)
    {
        $media = $this->get($entry);

        return $media->map(function ($media) use ($entry) {
            return $this->getMediaIdentifier($media, $entry);
        })->toArray();
    }

    private function saveUploadMultiple($entry, $value = null): void
    {
        $filesToDelete = CRUD::getRequest()->get('clear_'.$this->fieldName);

        $value = CRUD::getRequest()->file($this->fieldName);

        $previousFiles = $this->get($entry);

        if ($filesToDelete) {
            foreach ($previousFiles as $previousFile) {
                if (in_array($this->getMediaIdentifier($previousFile, $entry), $filesToDelete)) {
                    $previousFile->delete();
                }
            }
        }

        foreach ($value ?? [] as $file) {
            if ($file && is_file($file)) {
                $this->addMediaFile($entry, $file);
            }
        }
    }

    private function saveRepeatableUploadMultiple($entry): void
    {
        $previousFiles = array_column($this->getPreviousRepeatableMedia($entry),$this->fieldName);

        $filesToDelete = collect($this->getFromRequestAsArray('clear_'))->flatten()->toArray();
        $fileOrder = $this->getFromRequestAsArray('_order_', ',');

        $value = CRUD::getRequest()->file($this->parentField) ?? [];

        foreach ($value as $row => $rowValue) {
            foreach ($rowValue[$this->fieldName] ?? [] as $file) {
                if ($file && is_file($file)) {
                    $this->addMediaFile($entry, $file, $row);
                }
            }
        }

        foreach ($previousFiles as $file) {
            $previousFileIdentifier = $this->getMediaIdentifier($file, $entry);
            if (empty($fileOrder)) {
                $file->delete();
                continue;
            }

            if (in_array($previousFileIdentifier, $filesToDelete)) {
                $file->delete();

                continue;
            }

            foreach ($fileOrder as $row => $files) {
                if (is_array($files)) {
                    $key = array_search($previousFileIdentifier, $files, true);
                    if ($key !== false) {
                        $file->setCustomProperty('repeatableRow', $row);
                        $file->save();
                        // avoid checking the same file twice. This is a performance improvement.
                        unset($fileOrder[$row][$key]);
                    }
                }
                if (empty($fileOrder[$row])) {
                    unset($fileOrder[$row]);
                }
            }
        } 
    }

    private function getFromRequestAsArray(string $key, $delimiter = null): array
    {
        $items = CRUD::getRequest()->input($key.$this->parentField) ?? [];

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
