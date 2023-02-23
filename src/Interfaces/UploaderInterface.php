<?php

namespace Backpack\MediaLibraryUploads\Interfaces;

use Illuminate\Database\Eloquent\Model;

interface UploaderInterface
{
    public function getSavingEvent(Model $entry);

    public function getRetrievedEvent(Model $entry);

    public static function for(array $field, $definition): self;
}
