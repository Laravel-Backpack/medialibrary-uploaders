<?php

namespace Backpack\MediaLibraryUploaders\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Uploaders\Support\Interfaces\UploaderInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
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

        foreach ($values as $row => $files) {
            $files = is_array($files) ? $files : (json_decode($files, true) ?? []);

            $uploadedFiles = array_filter($files, function ($value) {
                return strpos($value, $this->temporaryFolder) !== false;
            });

            foreach ($uploadedFiles ?? [] as $key => $file) {
                try {
                    $name = substr($file, strrpos($file, '/') + 1);

                    $temporaryFile = $this->temporaryDisk->get($file);

                    $this->permanentDisk->put($this->getPath().$name, $temporaryFile);

                    $this->temporaryDisk->delete($file);

                    $file = str_replace(Str::finish($this->temporaryFolder, '/'), $this->getPath(), $file);

                    $values[$row][$key] = $file;
                } catch (\Throwable $th) {
                    Log::error($th->getMessage());
                    Alert::error('An error occurred uploading files. Check log files.')->flash();
                }
            }
        }

        $previousValuesArray = Arr::flatten(Arr::map($previousValues, function ($value) {
            return ! is_array($value) ? json_decode($value, true) ?? [] : $value;
        }));

        $currentValuesArray = Arr::flatten(Arr::map($values, function ($value) {
            return ! is_array($value) ? json_decode($value, true) ?? [] : $value;
        }));

        $filesToDelete = array_diff($previousValuesArray, $currentValuesArray);

        foreach ($filesToDelete as $key => $value) {
            $this->permanentDisk->delete($this->getPath().$value);
        }

        foreach ($values as $row => $value) {
            if (empty($value)) {
                unset($values[$row]);
            }
        }

        return $values;
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
