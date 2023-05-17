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
        $temporaryDisk = config('backpack.base.temporary_disk');
        $temporaryFolder = config('backpack.base.temporary_folder');

        $uploads = $value ?? CRUD::getRequest()->input($this->getName());

        if (! is_array($uploads) && is_string($uploads)) {
            $uploads = json_decode($uploads, true) ?? [];
        }

        $uploadedFiles = array_filter($uploads, function ($value) use ($temporaryFolder) {
            return strpos($value, $temporaryFolder) !== false;
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
        $temporaryFolder = config('backpack.base.temp_upload_folder_name') ?? 'backpack/temp/';
        $temporaryDisk = config('backpack.base.temp_disk_name') ?? 'public';

        $values = array_map(function ($value) {
            if (! is_array($value)) {
                $value = json_decode($value, true);
                // TODO: this array unique should be removed, there is an issue with JS in dropzone.blade.php
                $value = array_unique($value);
            }

            return $value;
        }, $values);

        $sentFiles = [];
        foreach ($values as $row => $files) {
            if (! is_array($files)) {
                $files = json_decode($files, true) ?? [];
            }
            $uploadedFiles = array_filter($files, function ($value) use ($temporaryFolder) {
                return strpos($value, $temporaryFolder) !== false;
            });

            $sentFiles = array_merge($sentFiles, array_filter($files, function ($value) use ($temporaryFolder) {
                return strpos($value, $temporaryFolder) === false;
            }));

            foreach ($uploadedFiles ?? [] as $key => $value) {
                $file = new File(Storage::disk($temporaryDisk)->path($value));
                $this->addMediaFile($entry, $file, $row);
            }
        }

        foreach ($previousValues as $previousFile) {
            $fileIdentifier = $this->getMediaIdentifier($previousFile, $entry);
            if (array_search($fileIdentifier, $sentFiles, true) === false) {
                $previousFile->delete();
            }
        }
    }
}
