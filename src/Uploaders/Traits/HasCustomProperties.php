<?php

namespace Backpack\MediaLibraryUploaders\Uploaders\Traits;

trait HasCustomProperties
{
    public function getCustomProperties()
    {
        return [
            'name'                    => $this->getName(),
            'repeatableContainerName' => $this->repeatableContainerName,
            'repeatableRow'           => $this->order,
        ];
    }
}