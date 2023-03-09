<?php

namespace Backpack\MediaLibraryUploads\Interfaces;

use Illuminate\Database\Eloquent\Model;

interface RepeatableUploaderInterface
{
    public function uploads(...$uploads): self;

    public static function for(array $field): self;

    public function processFileUpload(Model $entry);

    public function retrieveUploadedFile(Model $entry);
}
