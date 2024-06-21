<?php

namespace Backpack\MediaLibraryUploaders\Uploaders\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;

trait HasConstrainedFileAdder
{
    private function initFileAdder(Model $entry, File|UploadedFile|string $file)
    {
        if (is_a($file, UploadedFile::class, true)) {
            return $entry->addMedia($file);
        }

        if (is_string($file)) {
            return $entry->addMediaFromBase64($file);
        }

        if (get_class($file) === File::class) {
            return $entry->addMedia($file->getPathName());
        }
    }
}