<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageFieldUploader extends Uploader
{
    public function save(Model $entry, $value = null)
    {
        return $this->isRepeatable ? $this->saveRepeatableImage($entry, $value) : $this->saveImage($entry, $value);
    }

    private function saveImage($entry, $value = null)
    {
        $value = $value ?? CrudPanelFacade::getRequest()->get($this->fieldName);
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

    private function saveRepeatableImage($entry, $value): void
    {
        $previousImages = $this->get($entry);

        foreach ($value as $row => $rowValue) {
            if ($rowValue) {
                if (Str::startsWith($rowValue, 'data:image')) {
                    $this->addMediaFile($entry, $rowValue, $row);

                    continue;
                }

                $filename = Str::afterLast($rowValue, '/');
                $id = Str::afterLast(Str::beforeLast($rowValue, '/'), '/');

                $value[$row] = $id.'/'.$filename;

                $currentImage = $previousImages
                    ->where('id', $id)->where('file_name', $filename)->first();

                if ($currentImage && $currentImage->order_column !== $row) {
                    $currentImage->order_column = $row;
                    $currentImage->save();
                }
            }
        }

        foreach ($previousImages as $image) {
            $mediaIdentifier = $image->id.'/'.$image->file_name;

            if (! in_array($mediaIdentifier, $value)) {
                $image->delete();
            }
        }
    }
}
