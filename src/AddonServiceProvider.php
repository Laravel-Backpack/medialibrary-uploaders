<?php

namespace Backpack\MediaLibraryUploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudColumn;
use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\CRUD\app\Library\Uploaders\Support\RegisterUploadEvents;
use Backpack\MediaLibraryUploaders\Uploaders\MediaDropzoneUploader;
use Backpack\MediaLibraryUploaders\Uploaders\MediaMultipleFiles;
use Backpack\MediaLibraryUploaders\Uploaders\MediaSingleBase64Image;
use Backpack\MediaLibraryUploaders\Uploaders\MediaSingleFile;
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

        // add media uploaders to UploadersRepository.
        app('UploadersRepository')->addUploaderClasses([
            'image'           => MediaSingleBase64Image::class,
            'upload'          => MediaSingleFile::class,
            'upload_multiple' => MediaMultipleFiles::class,
        ], 'withMedia');

        if (class_exists(\Backpack\Pro\Uploads\BackpackAjaxUploader::class)) {
            app('UploadersRepository')->addUploaderClasses([
                'dropzone' => MediaDropzoneUploader::class,
            ], 'withMedia');
        }

        // register media upload macros on crud fields and columns.
        if (! CrudField::hasMacro('withMedia')) {
            CrudField::macro('withMedia', function ($uploadDefinition = [], $subfield = null, $registerEvents = true) {
                $uploadDefinition = is_array($uploadDefinition) ? $uploadDefinition : [];
                /** @var CrudField $this */
                RegisterUploadEvents::handle($this, $uploadDefinition, 'withMedia', $subfield, $registerEvents);

                return $this;
            });
        }

        if (! CrudColumn::hasMacro('withMedia')) {
            CrudColumn::macro('withMedia', function ($uploadDefinition = [], $subfield = null, $registerEvents = true) {
                $uploadDefinition = is_array($uploadDefinition) ? $uploadDefinition : [];
                /** @var CrudColumn $this */
                RegisterUploadEvents::handle($this, $uploadDefinition, 'withMedia', $subfield, $registerEvents);

                return $this;
            });
        }
    }
}
