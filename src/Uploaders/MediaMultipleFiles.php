<?php

namespace Backpack\MediaLibraryUploaders\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Uploaders\Support\Interfaces\UploaderInterface;
use Illuminate\Database\Eloquent\Model;

class MediaMultipleFiles extends MediaUploader
{
    public static function for(array $field, $configuration): UploaderInterface
    {
        return (new self($field, $configuration))->multiple();
    }

    public function uploadFiles(Model $entry, $value = null)
    {
        $filesToDelete = CRUD::getRequest()->get('clear_'.($this->repeatableContainerName ?? $this->getName())) ?? [];

        $filesToDelete = collect($filesToDelete)->flatten()->toArray();

        $value = $value ?? CRUD::getRequest()->file($this->getName());

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

    public function uploadRepeatableFiles($value, $previousValues, $entry = null)
    {
        $fileOrder = $this->getFileOrderFromRequest();

        foreach ($value as $row => $files) {
            foreach ($files ?? [] as $file) {
                if ($file && is_file($file)) {
                    $this->addMediaFile($entry, $file, $row);
                }
            }
        }

        foreach ($previousValues as $file) {
            $previousFileIdentifier = $this->getMediaIdentifier($file, $entry);
            if (empty($fileOrder)) {
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
}
