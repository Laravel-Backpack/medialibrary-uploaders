<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageFieldUploader extends Uploader
{
    public function save(Model $entry, $values = null)
    {
        return $this->isRepeatable ? $this->saveRepeatableImage($entry, $values) : $this->saveImage($entry, $values);
    }

    private function saveImage($entry)
    {
        $value = CrudPanelFacade::getRequest()->get($this->fieldName);
        $previousImage = $entry->getOriginal($this->fieldName);

        if (! $value && $previousImage) {
            Storage::disk($this->disk)->delete($previousImage);

            return null;
        }

        if (Str::startsWith($value, 'data:image')) {
            if ($previousImage) {
                Storage::disk($this->disk)->delete($previousImage);
            }

            $base64Image = Str::after($value, ';base64,');

            $finalPath = $this->path.$this->getFileName($value).'.'.$this->getExtensionFromFile($value);
            Storage::disk($this->disk)->put($finalPath, base64_decode($base64Image));

            return $finalPath;
        }

        return $previousImage;
    }

    private function saveRepeatableImage($entry, $values)
    {
        $previousImages = $this->getPreviousRepeatableValues($entry);

        array_walk($previousImages, function (&$item) {
            $item = Storage::disk($this->disk)->url($item);
        });

        foreach ($values as $row => $rowValue) {
            if ($rowValue) {
                if (Str::startsWith($rowValue, 'data:image')) {
                    $base64Image = Str::after($rowValue, ';base64,');
                    $finalPath = $this->path.$this->getFileName($rowValue).'.'.$this->getExtensionFromFile($rowValue);
                    Storage::disk($this->disk)->put($finalPath, base64_decode($base64Image));
                    $values[$row] = $finalPath;
                    $previousImages[] = $finalPath;

                    continue;
                }
            }
        }

        $imagesToDelete = array_diff($previousImages, $values);
        foreach ($imagesToDelete as $image) {
            Storage::disk($this->disk)->delete($image);
        }

        return $values;
    }
}
