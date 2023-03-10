<?php

namespace Backpack\MediaLibraryUploads\Traits;

trait HasName
{
    /**
     * The name of the uploader AKA CrudField/Column name.
     *
     * @var string
     */
    public string $name;

    /**
     * Return the crud object type, field or column
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}