<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Backpack\MediaLibraryUploads\ConstrainedFileAdder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaImageFieldUploader extends MediaUploader
{
    public function save(Model $entry, $value = null)
    {
        return $this->isRepeatable ? $this->saveRepeatableImage($entry, $value) : $this->saveImage($entry, $value);
    }

    private function saveImage($entry, $value = null): void
    {
        $value = $value ?? CrudPanelFacade::getRequest()->get($this->fieldName);

        $previousImage = $this->get($entry);

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
