<?php

use Backpack\MediaLibraryUploads\Uploaders\SingleBase64Image;
use Backpack\MediaLibraryUploads\Uploaders\SingleFile;
use Backpack\MediaLibraryUploads\Uploaders\MultipleFiles;
use Backpack\MediaLibraryUploads\Uploaders\RepeatableUploader;

return [
    'image'           => SingleBase64Image::class,
    'upload'          => SingleFile::class,
    'upload_multiple' => MultipleFiles::class,
    'repeatable'      => RepeatableUploader::class,
];
