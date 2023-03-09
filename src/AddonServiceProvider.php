<?php

namespace Backpack\MediaLibraryUploads;

use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\MediaLibraryUploads\Uploaders\MediaLibrary\MediaSingleBase64Image;
use Backpack\MediaLibraryUploads\Uploaders\MediaLibrary\MediaRepeatable;
use Backpack\MediaLibraryUploads\Uploaders\MediaLibrary\MediaSingleFile;
use Backpack\MediaLibraryUploads\Uploaders\MediaLibrary\MediaMultipleFiles;
use Backpack\MediaLibraryUploads\Uploaders\MultipleFiles;
use Backpack\MediaLibraryUploads\Uploaders\RelationshipUploader;
use Backpack\MediaLibraryUploads\Uploaders\RepeatableUploader;
use Backpack\MediaLibraryUploads\Uploaders\SingleBase64Image;
use Backpack\MediaLibraryUploads\Uploaders\SingleFile;
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
                'image'           => MediaSingleBase64Image::class,
                'upload'          => MediaSingleFile::class,
                'upload_multiple' => MediaMultipleFiles::class,
                'repeatable'      => MediaRepeatable::class,
                'relationship'    => RelationshipUploader::class
            ]);

            return $this;
        });

        // TODO: move to core
        CrudField::macro('withUploads', function ($uploadDefinition = null) {
            RegisterUploadEvents::handle($this, $uploadDefinition, [
                'image'           => SingleBase64Image::class,
                'upload'          => SingleFile::class,
                'upload_multiple' => MultipleFiles::class,
                'repeatable'      => RepeatableUploader::class,
                'relationship'    => RelationshipUploader::class
            ]);

            return $this;
        });
    }
}
