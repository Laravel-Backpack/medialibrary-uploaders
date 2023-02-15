<?php

namespace Backpack\MediaLibraryUploads;

use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ConstrainedFileAdder
{
    private $fileAdder;

    public function usingName(string $name): FileAdder
    {
        abort(500, 'usingName() should be configured using: ->withMedia([\'name\' => \''.$name.'\'])');
    }

    public function setName(string $name): FileAdder
    {
        abort(500, 'setName() should be configured using: ->withMedia([\'name\' => \''.$name.'\'])');
    }

    public function setOrder(?int $order): FileAdder
    {
        abort(500, 'Order is automatically assigned by Backpack functions.');
    }

    public function toMediaCollection(string $collectionName = 'default', string $diskName = ''): Media
    {
        abort(500, 'toMediaCollection() is automatically called by Backpack. You should configure it with: ->withMedia([\'collection\' => \''.$collectionName.'\', \'disk\' => \''.$diskName.'\'])');
    }

    public function toMediaCollectionFromRemote(string $collectionName = 'default', string $diskName = ''): Media
    {
        abort(500, 'toMediaCollection() is automatically called by Backpack. You should configure it with: ->withMedia([\'collection\' => \''.$collectionName.'\', \'disk\' => \''.$diskName.'\'])');
    }

    public function toMediaLibrary(string $collectionName = 'default', string $diskName = ''): Media
    {
        abort(500, 'toMediaCollection() is automatically called by Backpack. You should configure it with: ->withMedia([\'collection\' => \''.$collectionName.'\', \'disk\' => \''.$diskName.'\'])');
    }

    public function getFileAdder()
    {
        foreach(get_object_vars($this->fileAdder) as $key => $value) {
            if(!empty($this->{$key})) {
                $this->fileAdder->{$key} = $value;
            }
        }
        return $this->fileAdder;
    }

    public function setFileAdder(FileAdder $fileAdder)
    {
        $this->fileAdder = $fileAdder;
    }

    public function __call($method, $parameters)
    {
        if (method_exists($this, $method)) {
            return $this->{$method}(...$parameters);
        }
        
        $this->fileAdder->{$method}(...$parameters);
        return $this;
    }   
}
