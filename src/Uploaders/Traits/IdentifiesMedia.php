<?php

namespace Backpack\MediaLibraryUploaders\Uploaders\Traits;

use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

trait IdentifiesMedia
{
    public string $mediaName;

    public function getMediaIdentifier($media, $entry = null)
    {
        $path = PathGeneratorFactory::create($media);
        if ($entry && ! empty($entry->mediaConversions)) {
            $conversion = array_filter($entry->mediaConversions, function ($item) use ($media) {
                return $item->getName() === $this->getConversionToDisplay($media);
            })[0] ?? null;

            if (! $conversion) {
                return $path->getPath($media).$media->file_name;
            }

            return $path->getPathForConversions($media).$conversion->getConversionFile($media);
        }
        if (is_null($path)) {
            dd($media);
        }

        return $path->getPath($media).$media->file_name;
    }
}
