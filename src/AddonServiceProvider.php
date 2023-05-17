<?php

namespace Backpack\MediaLibraryUploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudColumn;
use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\CRUD\app\Library\Uploaders\Support\RegisterUploadEvents;
use Backpack\MediaLibraryUploaders\Uploaders\MediaAjaxUploader;
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

        //add media uploaders to UploadersRepository
        app('UploadersRepository')->addUploaderClasses([
            'image'           => MediaSingleBase64Image::class,
            'upload'          => MediaSingleFile::class,
            'upload_multiple' => MediaMultipleFiles::class,
            'dropzone'        => MediaAjaxUploader::class,
        ], 'withMedia');

        CrudField::macro('withMedia', function ($uploadDefinition = [], $subfield = null) {
            /** @var CrudField|CrudColumn $this */
            RegisterUploadEvents::handle($this, $uploadDefinition, 'withMedia', $subfield);

            return $this;
        });

        CrudColumn::macro('withMedia', function ($uploadDefinition = [], $subfield = null) {
            /** @var CrudField|CrudColumn $this */
            RegisterUploadEvents::handle($this, $uploadDefinition, 'withMedia', $subfield);

            return $this;
        });
    }
}
