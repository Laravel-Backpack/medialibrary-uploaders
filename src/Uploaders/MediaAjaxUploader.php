<?php

namespace Backpack\MediaLibraryUploaders\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Pro\Uploads\BackpackAjaxUploader;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;

class MediaAjaxUploader extends BackpackAjaxUploader
{
    use Traits\IdentifiesMedia;
    use Traits\AddMediaToModels;
    use Traits\HasConstrainedFileAdder;
    use Traits\HasCustomProperties;
    use Traits\HasSavingCallback;
    use Traits\HasCollections;
    use Traits\RetrievesUploadedFiles;
    use Traits\HandleRepeatableUploads;
    use Traits\DeletesUploadedFiles;

    public function __construct(array $crudObject, array $configuration)
    {
        
        $this->mediaName = $configuration['mediaName'] ?? $crudObject['name'];
        $this->savingEventCallback = $configuration['whenSaving'] ?? null;
        $this->collection = $configuration['collection'] ?? 'default';

        $this->displayConversions = $configuration['displayConversions'] ?? [];

        $modelDefinition = $this->getModelInstance($crudObject)->getRegisteredMediaCollections()
                            ->reject(function ($item) {
                                return $item->name !== $this->collection;
                            })
                            ->first();

        $configuration['disk'] ??= $modelDefinition?->diskName ?? null;

        $configuration['disk'] = empty($configuration['disk']) ? ($crudObject['disk'] ?? config('media-library.disk_name')) : $configuration['disk'];

        // read https://spatie.be/docs/laravel-medialibrary/v11/advanced-usage/using-a-custom-directory-structure#main
        // on how to customize file directory
        $crudObject['prefix'] = $configuration['path'] = '';

        parent::__construct($crudObject, $configuration);

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
            foreach ($sentFiles as $row => $sentFilesInRow) {
                $fileWasSent = array_search($fileIdentifier, $sentFilesInRow, true);
                if ($fileWasSent !== false) {
                    $foundInSentFiles = true;
                    if ($row !== $previousFile->getCustomProperty('repeatableRow')) {
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
