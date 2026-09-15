<?php

namespace Backpack\MediaLibraryUploaders\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Uploaders\Support\Interfaces\UploaderInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $uploads = $value ?? CRUD::getRequest()->input($this->getName()) ?? [];

        $uploads = is_array($uploads) ? $uploads : (json_decode($uploads, true) ?? []);

        $uploadedFiles = array_filter($uploads, function ($value) use ($temporaryFolder, $temporaryDisk) {
            return $this->isTemporaryFile($value, $temporaryFolder, $temporaryDisk);
        });

        $previousSentFiles = array_filter($uploads, function ($value) use ($temporaryFolder) {
            return ! $this->isInTemporaryFolder($value, $temporaryFolder);
        });

        // name the new files before changing anything, so a file that is not allowed does not remove or leave files behind
        $filesToAdd = [];

        foreach ($uploadedFiles as $key => $value) {
            $file = new File(Storage::disk($temporaryDisk)->path($value));
            $filesToAdd[] = [$file, $this->getFileName($file)];
        }

        $previousFiles = $this->get($entry);

        foreach ($previousFiles as $previousFile) {
            if (! in_array($this->getMediaIdentifier($previousFile, $entry), $previousSentFiles)) {
                $previousFile->delete();
            }
        }

        foreach ($filesToAdd as [$file, $fileName]) {
            $this->addMediaFile($entry, $file, null, $fileName);
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
        // name all the files before adding any, so a file that is not allowed does not leave the others behind
        $filesToAdd = [];
        foreach ($values as $row => $files) {
            if (! is_array($files)) {
                $files = json_decode($files, true) ?? [];
            }
            $uploadedFiles = array_filter($files, function ($value) use ($temporaryFolder, $temporaryDisk) {
                return $this->isTemporaryFile($value, $temporaryFolder, $temporaryDisk);
            });

            $sentFiles = array_merge($sentFiles, [$row => array_filter($files, function ($value) use ($temporaryFolder) {
                return ! $this->isInTemporaryFolder($value, $temporaryFolder);
            })]);

            foreach ($uploadedFiles ?? [] as $key => $value) {
                $file = new File(Storage::disk($temporaryDisk)->path($value));
                $filesToAdd[] = [$row, $file, $this->getFileName($file)];
            }
        }

        foreach ($filesToAdd as [$row, $file, $fileName]) {
            $this->addMediaFile($entry, $file, $row, $fileName);
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

    private function isInTemporaryFolder(mixed $value, mixed $temporaryFolder): bool
    {
        return is_string($value) && is_string($temporaryFolder) && $temporaryFolder !== '' && Str::startsWith($value, Str::finish($temporaryFolder, '/'));
    }

    /**
     * A temporary file must live inside the temporary folder, without parent directory segments.
     */
    private function isTemporaryFile(mixed $value, mixed $temporaryFolder, mixed $temporaryDisk): bool
    {
        if (! $this->isInTemporaryFolder($value, $temporaryFolder) || Str::startsWith($value, '..') || Str::contains($value, ['/..', '\\..'])) {
            return false;
        }

        return Storage::disk($temporaryDisk)->exists($value);
    }
}
