<?php

namespace Backpack\MediaLibraryUploaders\Tests\Config\Controllers;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\MediaLibraryUploaders\Tests\Config\Models\MediaUploader;

class MediaUploaderCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        parent::setup();

        $this->crud->setRoute(config('backpack.base.route_prefix').'/media-uploader');
        $this->crud->setModel(MediaUploader::class);
    }

    protected function setupCreateOperation()
    {
        CRUD::field('upload')->type('upload')->withMedia([
            'disk' => 'uploaders', 
            'fileNamer' => fn ($file) => $file->getClientOriginalName(),
            'whenSaving' => function($spatieMedia, $backpackMediaObject) {
                return $spatieMedia->preservingOriginal();
            }
        ]);
        CRUD::field('upload_multiple')->type('upload_multiple')->withMedia([
            'disk' => 'uploaders', 
            'fileNamer' => fn ($file) => $file->getClientOriginalName(),
            'whenSaving' => function($spatieMedia, $backpackMediaObject) {
                return $spatieMedia->preservingOriginal();
            }
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupDeleteOperation()
    {
        $this->setupCreateOperation();
    }
}
