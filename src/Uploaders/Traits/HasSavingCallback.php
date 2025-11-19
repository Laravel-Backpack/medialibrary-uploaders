<?php

namespace Backpack\MediaLibraryUploaders\Uploaders\Traits;

use Closure;

trait HasSavingCallback
{
    public null|Closure $savingEventCallback = null;
}