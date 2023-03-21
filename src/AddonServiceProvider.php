<?php

namespace Backpack\MediaLibraryUploads;

use Backpack\CRUD\app\Library\CrudPanel\CrudColumn;
use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\CRUD\app\Library\CrudPanel\Uploads\RegisterUploadEvents;
use Backpack\MediaLibraryUploads\Uploaders\MediaLibrary\MediaMultipleFiles;
use Backpack\MediaLibraryUploads\Uploaders\MediaLibrary\MediaRepeatable;
use Backpack\MediaLibraryUploads\Uploaders\MediaLibrary\MediaSingleBase64Image;
use Backpack\MediaLibraryUploads\Uploaders\MediaLibrary\MediaSingleFile;
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
        app('UploadStore')->addUploaders([
            'image'           => MediaSingleBase64Image::class,
            'upload'          => MediaSingleFile::class,
            'upload_multiple' => MediaMultipleFiles::class,
            'repeatable'      => MediaRepeatable::class,
        ], 'media');

        CrudField::macro('withMedia', function ($uploadDefinition = []) {
            $uploadDefinition['uploaders'] = 'media';

            /** @var CrudField|CrudColumn $this */
            RegisterUploadEvents::handle($this, $uploadDefinition);

            return $this;
        });

        CrudColumn::macro('withMedia', function ($uploadDefinition = []) {
            $uploadDefinition['uploaders'] = 'media';

            /** @var CrudField|CrudColumn $this */
            RegisterUploadEvents::handle($this, $uploadDefinition);

            return $this;
        });
    }
}
