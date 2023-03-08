<?php

namespace Backpack\MediaLibraryUploads;

use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\MediaLibraryUploads\Uploaders\Repeatable;
use Backpack\MediaLibraryUploads\Uploaders\MultipleFileUploader;
use Backpack\MediaLibraryUploads\Uploaders\SingleFileUploader;
use Backpack\MediaLibraryUploads\Uploaders\SingleBase64Uploader;


use Backpack\MediaLibraryUploads\Uploaders\MediaRepeatable;
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
                'repeatable' => MediaRepeatable::class
            ]);
            return $this;
        });

        // TODO: move to core
        CrudField::macro('withUploads', function ($uploadDefinition = null) {
            RegisterUploadEvents::handle($this, $uploadDefinition, [
                'image' => SingleBase64Uploader::class,
                'upload' => SingleFileUploader::class,
                'upload_multiple' => MultipleFileUploader::class,
                'repeatable' => Repeatable::class

            ]);

            return $this;
        });
    }
}
