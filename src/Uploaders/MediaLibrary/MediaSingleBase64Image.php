<?php

namespace Backpack\MediaLibraryUploads\Uploaders\MediaLibrary;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Backpack\MediaLibraryUploads\ConstrainedFileAdder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaSingleBase64Image extends MediaUploader
{
    public function save(Model $entry, $value = null)
    {
        return $this->isRepeatable && ! $this->isRelationship ? $this->saveRepeatableSingleBase64Image($entry, $value) : $this->saveSingleBase64Image($entry, $value);
    }

    private function saveSingleBase64Image($entry, $value = null): void
    {
        dump($value);
        $value = $value ?? CrudPanelFacade::getRequest()->get($this->fieldName);
        dump($value);
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

    private function saveRepeatableSingleBase64Image($entry, $value): void
    {
        $previousImages = array_column($this->getPreviousRepeatableMedia($entry),$this->fieldName);
    
        foreach ($value as $row => $rowValue) {
            if ($rowValue) {
                if (Str::startsWith($rowValue, 'data:image')) {
                    $this->addMediaFile($entry, $rowValue, $row);
                    continue;
                }

                $currentImage = $this->getMediaFromFileUrl($previousImages, $rowValue, $entry);
             
                if ($currentImage) {
                    $value[$row] = $this->getMediaIdentifier($currentImage, $entry);
                    if ($currentImage->getCustomProperty('repeatableRow') !== $row) {
                        $currentImage->setCustomProperty('repeatableRow', $row);
                        $currentImage->save();
                    }
                }
            }
        }

        foreach ($previousImages as $image) {
            if (! in_array($this->getMediaIdentifier($image, $entry), $value)) {
                $image->delete();
            }
        }
    }

    private function getMediaFromFileUrl($previousImages, $fileUrl, $entry)
    {    
        $previousImage = array_filter($previousImages, function($image) use ($fileUrl, $entry) {
            return Str::endsWith($fileUrl, $this->getMediaIdentifier($image, $entry));
        });
        
        return is_array($previousImage) ? array_shift($previousImage) : null;
    }
}
