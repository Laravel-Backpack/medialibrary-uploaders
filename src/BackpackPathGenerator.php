<?php

namespace Backpack\MediaLibraryUploads;

use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class BackpackPathGenerator implements PathGenerator
{
    private $uploadersPaths = [];

    public function addUploaderPath($uploader, $path) {
        $this->uploadersPaths[$uploader] = $path;
    }
    /*
     * Get the path for the given media, relative to the root storage path.
     */
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    /*
     * Get the path for conversions of the given media, relative to the root storage path.
     */
    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'/conversions/';
    }

    /*
     * Get the path for responsive images of the given media, relative to the root storage path.
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'/responsive-images/';
    }

    /*
     * Get a unique base path for the given media.
     */
    protected function getBasePath(Media $media): string
    {
        $fieldName = $media->getCustomProperty('name');

        $backpackPrefix = $this->uploadersPaths[$fieldName] ?? '';
        $spatiePrefix = config('media-library.prefix', '');

        if ($backpackPrefix !== '') {
            $url = $backpackPrefix.$media->getKey();
        }

        if ($spatiePrefix !== '') {
            $url = $spatiePrefix . '/' . ($url ?? $media->getKey());
        }

        return $url ?? $media->getKey();
    }
}
