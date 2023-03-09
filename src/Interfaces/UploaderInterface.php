<?php

namespace Backpack\MediaLibraryUploads\Interfaces;

use Illuminate\Database\Eloquent\Model;

interface UploaderInterface
{
    public function processFileUpload(Model $entry);

    public function retrieveUploadedFile(Model $entry);

    public static function for(array $field, $definition);
}
