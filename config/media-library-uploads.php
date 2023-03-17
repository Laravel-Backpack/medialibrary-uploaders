<?php

return [
    'image'           => \Backpack\MediaLibraryUploads\Uploaders\SingleBase64Image::class,
    'upload'          => \Backpack\MediaLibraryUploads\Uploaders\SingleFile::class,
    'upload_multiple' => \Backpack\MediaLibraryUploads\Uploaders\MultipleFiles::class,
    'repeatable'      => \Backpack\MediaLibraryUploads\Uploaders\RepeatableUploader::class,
];
