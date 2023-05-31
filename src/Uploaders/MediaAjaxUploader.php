<?php

namespace Backpack\MediaLibraryUploaders\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Uploaders\Support\Interfaces\UploaderInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;

class MediaAjaxUploader extends MediaUploader
{
    public static function for(array $field, $configuration): UploaderInterface
    {
        return (new self($field, $configuration))->multiple();
    }

    public function uploadFiles(Model $entry, $value = null)
    {
        $temporaryDisk = CRUD::get('dropzone.temporary_disk');
        $temporaryFolder = CRUD::get('dropzone.temporary_folder');

        $uploads = $value ?? CRUD::getRequest()->input($this->getName());

        if (! is_array($uploads) && is_string($uploads)) {
            $uploads = json_decode($uploads, true) ?? [];
        }

        $uploadedFiles = array_filter($uploads, function ($value) use ($temporaryFolder, $temporaryDisk) {
            return strpos($value, $temporaryFolder) !== false && Storage::disk($temporaryDisk)->exists($value);
        });

        $previousSentFiles = array_filter($uploads, function ($value) use ($temporaryFolder) {
            return strpos($value, $temporaryFolder) === false;
        });

        $previousFiles = $this->get($entry);

        foreach ($previousFiles as $previousFile) {
            if (! in_array($this->getMediaIdentifier($previousFile, $entry), $previousSentFiles)) {
                $previousFile->delete();
            }
        }

        foreach ($uploadedFiles as $key => $value) {
            $file = new File(Storage::disk($temporaryDisk)->path($value));

            $this->addMediaFile($entry, $file);
        }
    }

    public function uploadRepeatableFiles($values, $previousValues, $entry = null)
    {
        $temporaryDisk = CRUD::get('dropzone.temporary_disk');
        $temporaryFolder = CRUD::get('dropzone.temporary_folder');

        $values = array_map(function ($value) {
            if (! is_array($value)) {
                $value = json_decode($value, true);
            }

            return $value;
        }, $values);

        $sentFiles = [];
        foreach ($values as $row => $files) {
            if (! is_array($files)) {
                $files = json_decode($files, true) ?? [];
            }
            $uploadedFiles = array_filter($files, function ($value) use ($temporaryFolder, $temporaryDisk) {
                return strpos($value, $temporaryFolder) !== false && Storage::disk($temporaryDisk)->exists($value);
            });

            $sentFiles = array_merge($sentFiles, [$row => array_filter($files, function ($value) use ($temporaryFolder) {
                return strpos($value, $temporaryFolder) === false;
            })]);

            foreach ($uploadedFiles ?? [] as $key => $value) {
                $file = new File(Storage::disk($temporaryDisk)->path($value));
                $this->addMediaFile($entry, $file, $row);
            }
        }

        foreach ($previousValues as $previousFile) {
            $fileIdentifier = $this->getMediaIdentifier($previousFile, $entry);
            if (empty($sentFiles)) {
                $previousFile->delete();
                continue;
            }
            
            $foundInSentFiles = false;
            foreach($sentFiles as $row => $sentFilesInRow) {
                $fileWasSent = array_search($fileIdentifier, $sentFilesInRow, true);
                if($fileWasSent !== false) {
                    $foundInSentFiles = true;
                    if($row !== $previousFile->getCustomProperty('repeatableRow')) {
                        $previousFile->setCustomProperty('repeatableRow', $row);
                        $previousFile->save();
                        // avoid checking the same file twice. This is a performance improvement.
                        unset($sentFiles[$row][$fileWasSent]);
                        break;
                    }
                }
            }

            if ($foundInSentFiles === false) {
                    $previousFile->delete();
            }
        }
    }
}
