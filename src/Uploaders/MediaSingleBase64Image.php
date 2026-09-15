<?php

namespace Backpack\MediaLibraryUploaders\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MediaSingleBase64Image extends MediaUploader
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];

    private function isValidBase64Image(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('#^data:image/(jpeg|png|gif|webp|avif);base64,#i', $value)) {
            return false;
        }

        $decoded = base64_decode(Str::after($value, ';base64,'), true);
        if ($decoded === false) {
            return false;
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($decoded);

        return in_array($detected, self::ALLOWED_MIME_TYPES, true);
    }

    public function uploadFiles(Model $entry, $value = null)
    {
        $value = $value ?? CrudPanelFacade::getRequest()->get($this->getName());

        $previousImage = $this->getPreviousFiles($entry);

        if (is_a($previousImage, Collection::class, true)) {
            $previousImage = null;
        }

        if (! $value && $previousImage) {
            $previousImage->delete();

            return;
        }

        if ($this->isValidBase64Image($value)) {
            // get the name first, so an image that is not allowed does not remove the previous one
            $fileName = $this->getFileName($value);

            if ($previousImage) {
                $previousImage->delete();
            }
            $this->addMediaFile($entry, $value, null, $fileName);
        }
    }

    public function uploadRepeatableFiles($value, $previousValues, $entry = null)
    {
        // name all the images before adding any, so an image that is not allowed does not leave the others behind
        $imagesToAdd = [];

        foreach ($value as $row => $rowValue) {
            if ($this->isValidBase64Image($rowValue)) {
                $imagesToAdd[$row] = $this->getFileName($rowValue);
            }
        }

        foreach ($value as $row => $rowValue) {
            if ($rowValue) {
                if (is_string($rowValue) && Str::startsWith($rowValue, 'data:')) {
                    // images that are not valid are not added, and replace the previous image in the row
                    if (isset($imagesToAdd[$row])) {
                        $this->addMediaFile($entry, $rowValue, $row, $imagesToAdd[$row]);
                    }

                    continue;
                }

                $currentImage = $this->getMediaFromFileUrl($previousValues, $rowValue, $entry);

                if ($currentImage) {
                    $value[$row] = $this->getMediaIdentifier($currentImage, $entry);
                    if ($currentImage->getCustomProperty('repeatableRow') !== $row) {
                        $currentImage->setCustomProperty('repeatableRow', $row);
                        $currentImage->save();
                    }
                }
            }
        }

        foreach ($previousValues as $image) {
            if (! in_array($this->getMediaIdentifier($image, $entry), $value)) {
                $image->delete();
            }
        }
    }

    private function getMediaFromFileUrl($previousImages, $fileUrl, $entry)
    {
        $previousImage = array_filter($previousImages, function ($image) use ($fileUrl, $entry) {
            return Str::endsWith($fileUrl, $this->getMediaIdentifier($image, $entry));
        });

        return is_array($previousImage) ? array_shift($previousImage) : null;
    }

    public function shouldUploadFiles($value): bool
    {
        return $value && is_string($value) && (bool) preg_match('#^data:image/(jpeg|png|gif|webp|avif);base64,#i', $value);
    }

    public function shouldKeepPreviousValueUnchanged(Model $entry, $entryValue): bool
    {
        return $entry->exists && is_string($entryValue) && ! Str::startsWith($entryValue, 'data:image');
    }
}
