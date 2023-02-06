<?php

namespace Backpack\MediaLibraryUploads;

use Backpack\CRUD\app\Library\CrudPanel\CrudField;
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

        CrudField::macro('withMedia', function ($saveCallback = null, $getCallback = null) {
            if (isset($this->getAttributes()['subfields'])) {
                MediaUploads::handleRepeatableUploads($this, $saveCallback, $getCallback);
                return;
            }

            MediaUploads::handleUploads($this, $saveCallback, $getCallback);
        });
    }

    
}
