<?php

namespace Backpack\MediaLibraryUploads;

use Backpack\CRUD\app\Library\CrudPanel\CrudColumn;
use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\CRUD\app\Library\Uploaders\Support\RegisterUploadEvents;
use Backpack\MediaLibraryUploads\Uploaders\MediaMultipleFiles;
use Backpack\MediaLibraryUploads\Uploaders\MediaRepeatable;
use Backpack\MediaLibraryUploads\Uploaders\MediaSingleBase64Image;
use Backpack\MediaLibraryUploads\Uploaders\MediaSingleFile;
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

        //add media uploaders to UploadStore
        app('UploadersRepository')->addUploaderClasses([
            'image'           => MediaSingleBase64Image::class,
            'upload'          => MediaSingleFile::class,
            'upload_multiple' => MediaMultipleFiles::class,
            'repeatable'      => MediaRepeatable::class,
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
