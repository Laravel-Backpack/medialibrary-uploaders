<?php

namespace Backpack\MediaLibraryUploads;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ImageField extends MediaField
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

            $extension = Str::after(mime_content_type($value), 'image/');

            $media = $this->addMediaFileFromBase64($entry, $value, $extension);

            /** @var \Spatie\MediaLibrary\MediaCollections\FileAdder $constrainedMedia */
            $constrainedMedia = new ConstrainedFileAdder(null);
            $constrainedMedia->setFileAdder($media);

            if ($this->savingEventCallback && is_callable($this->savingEventCallback)) {
                $constrainedMedia = call_user_func_array($this->savingEventCallback, [$constrainedMedia, $this]);
            }

            $constrainedMedia->getFileAdder()->toMediaCollection($this->collection, $this->disk);
        }
    }

    private function saveRepeatableImage($entry, $value): void
    {
        $previousImages = $this->get($entry);

        foreach ($value as $row => $rowValue) {
            if ($rowValue) {
                if (Str::startsWith($rowValue, 'data:image')) {
                    $extension = Str::after(mime_content_type($rowValue), 'image/');

                    $media = $this->addMediaFileFromBase64($entry, $rowValue, $extension);
                    $media = $media->setOrder($row);

                    /** @var \Spatie\MediaLibrary\MediaCollections\FileAdder $constrainedMedia */
                    $constrainedMedia = new ConstrainedFileAdder(null);
                    $constrainedMedia->setFileAdder($media);
                    
                    if ($this->savingEventCallback && is_callable($this->savingEventCallback)) {
                        $constrainedMedia = call_user_func_array($this->savingEventCallback, [&$constrainedMedia, $this]);
                       
                    }

                    $constrainedMedia->getFileAdder()->toMediaCollection($this->collection, $this->disk);

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

    public function getForDisplay(Model $entry): ?string
    {
        $image = $this->get($entry);

        if ($image) {
            return $image->getUrl();
        }

        return null;
    }
}
