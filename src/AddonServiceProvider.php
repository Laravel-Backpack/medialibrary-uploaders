<?php

namespace Backpack\MediaLibraryUploads;

use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\MediaLibraryUploads\Uploaders\RepeatableUploads;
use Backpack\MediaLibraryUploads\Uploaders\UploadMultipleFieldUploader;
use Backpack\MediaLibraryUploads\Uploaders\UploadFieldUploader;
use Backpack\MediaLibraryUploads\Uploaders\ImageFieldUploader;


use Backpack\MediaLibraryUploads\Uploaders\MediaRepeatableUploads;
use Backpack\MediaLibraryUploads\Uploaders\MediaUploadFieldUploader;
use Backpack\MediaLibraryUploads\Uploaders\MediaUploadMultipleFieldUploader;
use Backpack\MediaLibraryUploads\Uploaders\MediaImageFieldUploader;


use Illuminate\Support\ServiceProvider;

class AddonServiceProvider extends ServiceProvider
{
    use AutomaticServiceProvider;

    protected $vendorName = 'backpack';

    protected $packageName = 'media-library-uploads';

    protected $commands = [];

    public function boot()
    {
        $this->autoboot();

        CrudField::macro('withMedia', function ($mediaDefinition = null) {
            RegisterUploadEvents::handle($this, $mediaDefinition, [
                'image' => MediaImageFieldUploader::class,
                'upload' => MediaUploadFieldUploader::class,
                'upload_multiple' => MediaUploadMultipleFieldUploader::class,
                'repeatable' => MediaRepeatableUploads::class
            ]);
        });

        // TODO: move to core
        CrudField::macro('withUploads', function ($uploadDefinition = null) {
            RegisterUploadEvents::handle($this, $uploadDefinition, [
                'image' => ImageFieldUploader::class,
                'upload' => UploadFieldUploader::class,
                'upload_multiple' => UploadMultipleFieldUploader::class,
                'repeatable' => RepeatableUploads::class

            ]);
        });
    }
}
