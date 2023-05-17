<?php

namespace Backpack\MediaLibraryUploaders;

use Backpack\CRUD\app\Library\Uploaders\Support\Interfaces\UploaderInterface;
use Spatie\MediaLibrary\MediaCollections\FileAdder;

/**
 * @mixin \Spatie\MediaLibrary\MediaCollections\FileAdder
 */
class ConstrainedFileAdder
{
    private $fileAdder;

    private $uploader;

    public function withCustomProperties(array $properties)
    {
        $customProperties = array_merge($properties, $this->uploader->getCustomProperties());
        $this->fileAdder->withCustomProperties($customProperties);

        return $this;
    }

    public function toMediaCollection(string $collectionName = 'default', string $diskName = '')
    {
        abort(500, 'toMediaCollection() is automatically called by Backpack. You should configure it with: ->withMedia([\'collection\' => \''.$collectionName.'\', \'disk\' => \''.$diskName.'\'])');
    }

    public function toMediaCollectionFromRemote(string $collectionName = 'default', string $diskName = '')
    {
        abort(500, 'toMediaCollection() is automatically called by Backpack. You should configure it with: ->withMedia([\'collection\' => \''.$collectionName.'\', \'disk\' => \''.$diskName.'\'])');
    }

    public function toMediaLibrary(string $collectionName = 'default', string $diskName = '')
    {
        abort(500, 'toMediaCollection() is automatically called by Backpack. You should configure it with: ->withMedia([\'collection\' => \''.$collectionName.'\', \'disk\' => \''.$diskName.'\'])');
    }

    public function toMediaCollectionOnCloudDisk(string $collectionName = 'default')
    {
        abort(500, 'toMediaCollection() is automatically called by Backpack. You should configure it with: ->withMedia([\'collection\' => \''.$collectionName.'\'])');
    }

    public function getFileAdder()
    {
        foreach (get_object_vars($this->fileAdder) as $key => $value) {
            if (! empty($this->{$key})) {
                $this->fileAdder->{$key} = $value;
            }
        }

        return $this->fileAdder;
    }

    public function setFileAdder(FileAdder $fileAdder)
    {
        $this->fileAdder = $fileAdder;
    }

    public function setMediaUploader(UploaderInterface $uploader)
    {
        $this->uploader = $uploader;
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
