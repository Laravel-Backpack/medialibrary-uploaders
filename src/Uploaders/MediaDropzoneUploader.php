<?php

namespace Backpack\MediaLibraryUploaders\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Uploaders\Support\Interfaces\UploaderInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Prologue\Alerts\Facades\Alert;
use Illuminate\Http\UploadedFile;

class MediaDropzoneUploader extends MediaAjaxUploader
{
    public static function for(array $field, $configuration): UploaderInterface
    {
        return (new self($field, $configuration))->multiple();
    }

    public function uploadFiles(Model $entry, $value = null)
    {
        $uploads = $value ?? CRUD::getRequest()->input($this->getName()) ?? [];

        $uploads = is_array($uploads) ? $uploads : (json_decode($uploads, true) ?? []);

        $uploadedFiles = array_filter($uploads, function ($value) {
            return strpos($value, $this->temporaryFolder) !== false;
        });

        $previousSentFiles = array_filter($uploads, function ($value) {
            return strpos($value, $this->temporaryFolder) === false;
        });

        $previousDatabaseFiles = $this->getPreviousFiles($entry) ?? [];

        foreach ($previousDatabaseFiles as $previousFile) {
            if (! in_array($this->getMediaIdentifier($previousFile, $entry), $previousSentFiles)) {
                $previousFile->delete();
            }
        }

        foreach ($uploadedFiles as $key => $value) {
            $file = new UploadedFile($this->temporaryDisk->path($value), $value);
            $this->addMediaFile($entry, $file);
        }
    }

    public function uploadRepeatableFiles($values, $previousValues, $entry = null)
    {
        $values = array_map(function ($value) {
            return is_array($value) ? $value : (json_decode($value, true) ?? []);
        }, $values);

        $sentFiles = [];

        foreach ($values as $row => $files) {
            $files = is_array($files) ? $files : (json_decode($files, true) ?? []);

            $uploadedFiles = array_filter($files, function ($value) {
                return strpos($value, $this->temporaryFolder) !== false;
            });

            $sentFiles = array_merge($sentFiles, [$row => array_filter($files, function ($value) {
                return strpos($value, $this->temporaryFolder) === false;
            })]);

            foreach ($uploadedFiles ?? [] as $key => $file) {
                try {
                    $file = new UploadedFile($this->temporaryDisk->path($file), $file);
                    $this->addMediaFile($entry, $file, $row);
                } catch (\Throwable $th) {
                    Log::error($th->getMessage());
                    Alert::error('An error occurred uploading files. Check log files.')->flash();
                }
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

    protected function ajaxEndpointSuccessResponse($files = null): \Illuminate\Http\JsonResponse
    {
        return $files ?
            response()->json(['files'   => $files, 'success' => true]) :
            response()->json(['success' => true]);
    }

    protected function ajaxEndpointErrorMessage(string $message = 'An error occurred while processing the file.'): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message'   => $message,
            'success'   => false,
        ], 400);
    }

    protected function buildAjaxEndpointValidationFilesArray($validationKey, $uploadedFiles, $requestInputName): array
    {
        $previousUploadedFiles = json_decode(CRUD::getRequest()->input('previousUploadedFiles'), true) ?? [];

        if (Str::contains($validationKey, '.*.')) {
            return [
                'validate_ajax_endpoint'          => true,
                Str::before($validationKey, '.*') => [
                    0 => [
                        Str::after($validationKey, '*.') => array_merge($uploadedFiles[$requestInputName], $previousUploadedFiles),
                    ],
                ],
            ];
        }

        return array_merge($uploadedFiles[$requestInputName], $previousUploadedFiles, ['validate_ajax_endpoint' => true]);
    }
}
