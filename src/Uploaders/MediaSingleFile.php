<?php

namespace Backpack\MediaLibraryUploaders\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class MediaSingleFile extends MediaUploader
{
    public function uploadFiles(Model $entry, $value = null)
    {
        $value = $value ?? CRUD::getRequest()->file($this->getName());

        $previousFile = $this->get($entry);

        // get the name first, so a file that is not allowed does not remove the previous one
        $fileName = $value && is_a($value, UploadedFile::class) ? $this->getFileName($value) : null;

        if ($previousFile && ($fileName !== null || request()->has($this->getName()))) {
            $previousFile->delete();
        }

        if ($fileName !== null) {
            $this->addMediaFile($entry, $value, null, $fileName);
        }
    }

    public function uploadRepeatableFiles($values, $previousFiles, $entry = null)
    {
        $values = CRUD::getRequest()->file($this->repeatableContainerName) ?? [];

        $filesToClear = CRUD::getRequest()->get('clear_'.$this->getRepeatableContainerName()) ?? [];
        $orderedFiles = $this->getFileOrderFromRequest();

        // name all the files before adding any, so a file that is not allowed does not leave the others behind
        $filesToAdd = [];

        foreach ($values as $row => $rowValue) {
            if (isset($rowValue[$this->getName()]) && is_file($rowValue[$this->getName()])) {
                $filesToAdd[$row] = [$rowValue[$this->getName()], $this->getFileName($rowValue[$this->getName()])];
            }
        }

        foreach ($filesToAdd as $row => [$file, $fileName]) {
            $this->addMediaFile($entry, $file, $row, $fileName);
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
}
