<?php

namespace Backpack\MediaLibraryUploads\Traits;

trait HasCrudObjectType
{
    /**
     * The type of the object upload is handling: field or column.
     *
     * @var string
     */
    public string $crudObjectType;

    /**
     * Return the crud object type, field or column
     *
     * @return string
     */
    public function getCrudObjectType()
    {
        return $this->crudObjectType;
    }
}