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
     * Return the name of the uploader, field or column
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}