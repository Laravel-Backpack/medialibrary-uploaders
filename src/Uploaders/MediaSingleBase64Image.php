<?php

namespace Backpack\MediaLibraryUploaders\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class MediaSingleBase64Image extends MediaUploader
{
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

        if (Str::startsWith($value, 'data:image')) {
            if ($previousImage) {
                $previousImage->delete();
            }
            $this->addMediaFile($entry, $value);
        }
    }

    public function uploadRepeatableFiles($value, $previousValues, $entry = null)
    {
        foreach ($value as $row => $rowValue) {
            if ($rowValue) {
                if (Str::startsWith($rowValue, 'data:image')) {
                    $this->addMediaFile($entry, $rowValue, $row);

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

    protected function shouldUploadFiles($value): bool
    {
        return $value && is_string($value) && Str::startsWith($value, 'data:image');
    }

    public function shouldKeepPreviousValueUnchanged(Model $entry, $entryValue): bool
    {
        return $entry->exists && is_string($entryValue) && ! Str::startsWith($entryValue, 'data:image');
    }
}
