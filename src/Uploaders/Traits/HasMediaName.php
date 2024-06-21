<?php

namespace Backpack\MediaLibraryUploaders\Uploaders\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;

trait HasMediaName
{
    public string $mediaName;
}