<?php

namespace Backpack\MediaLibraryUploads;

use Backpack\CRUD\app\Library\CrudPanel\CrudColumn;
use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

final class UploadStore
{
    private array $uploaders;

    private array $handledUploaders = [];

    public function __construct()
    {
        $this->uploaders = config('backpack.media-library-uploads');
    }

    public function markAsHandled(string $objectName)
    {
        $this->handledUploaders[] = $objectName;
    }

    public function isUploadHandled(string $objectName)
    {
        return in_array($objectName, $this->handledUploaders);
    }

    public function hasUploadFor(string $objectType) {
        return array_key_exists($objectType, $this->uploaders);
    }

    public function getUploadFor(string $objectType) {
        return $this->uploaders[$objectType];
    }

    public function addUploaders(array $uploaders)
    {
        $this->uploaders = array_merge($this->uploaders, $uploaders);
    }
}
