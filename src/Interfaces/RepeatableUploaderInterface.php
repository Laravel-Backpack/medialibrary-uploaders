<?php

namespace Backpack\MediaLibraryUploads\Interfaces;

interface RepeatableUploaderInterface
{
    public function uploads(... $uploads): self;
}
