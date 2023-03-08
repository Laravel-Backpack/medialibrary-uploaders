<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class UploadMultipleFieldUploader extends Uploader
{
    public static function for(array $field, $configuration): self
    {
        return (new static($field, $configuration))->multiple();
    }

    public function save(Model $entry, $value = null)
    {
        return $this->isRepeatable ? $this->saveRepeatableUploadMultiple($entry, $value) : $this->saveUploadMultiple($entry, $value);
    }

    private function saveUploadMultiple($entry, $value = null)
    {
        $filesToDelete = request()->get('clear_'.$this->fieldName);

        $value = request()->file($this->fieldName);

        $previousFiles = $entry->getOriginal($this->fieldName) ?? [];

        if ($filesToDelete) {
            foreach ($previousFiles as $previousFile) {
                if (in_array($previousFile, $filesToDelete)) {
                    Storage::disk($this->disk)->delete($previousFile);

                    $previousFiles = Arr::where($previousFiles, function ($value, $key) use ($previousFile) {
                        return $value != $previousFile;
                    });
                }
            }
        }

        foreach ($value ?? [] as $file) {
            if ($file && is_file($file)) {
                $finalPath = $this->path.$this->getFileName($file).'.'.$this->getExtensionFromFile($file);

                Storage::disk($this->disk)->put($finalPath, $file);

                $previousFiles[] = $finalPath;
            }
        }

        return isset($entry->getCasts()[$this->fieldName]) ? $previousFiles : json_encode($previousFiles);
    }

    private function saveRepeatableUploadMultiple($entry)
    {
        $previousFiles = $this->getPreviousRepeatableValues($entry);

        $filesToDelete = collect($this->getFromRequestAsArray('clear_'))->flatten()->toArray();
        $fileOrder = $this->getFromRequestAsArray('_order_', ',');

        $files = CrudPanelFacade::getRequest()->file($this->parentField) ?? [];

        foreach ($files as $row => $rowValue) {
            foreach ($rowValue[$this->fieldName] ?? [] as $file) {
                if ($file && is_file($file)) {
                    $finalPath = $this->path.$this->getFileName($file).'.'.$this->getExtensionFromFile($file);

                    Storage::disk($this->disk)->put($finalPath, $file);
                    $fileOrder[$row][] = $finalPath;
                }
            }
        }

        foreach ($previousFiles as $previousRow => $files) {
            foreach ($files as $key => $file) {
                $key = array_search($file, $fileOrder, true);
                if ($key === false) {
                    Storage::disk($this->disk)->delete($file);
                }
            }
        }

        return $fileOrder;
    }

    private function getFromRequestAsArray(string $key, $delimiter = null): array
    {
        $items = CrudPanelFacade::getRequest()->input($key.$this->parentField) ?? [];

        array_walk($items, function (&$key, $value) use ($delimiter) {
            $requestValue = $key[$this->fieldName] ?? null;
            if (is_string($requestValue) && $delimiter) {
                $key = explode($delimiter, $requestValue);
            } else {
                $key = $requestValue;
            }
        });

        return $items;
    }

    public function getRepeatableItemsAsArray($entry)
    {
        return $this->get($entry)->groupBy('order_column')->transform(function ($media) {
            $items = $media->map(function ($item) {
                return $item->getUrl();
            })->toArray();

            return [$this->fieldName => $items];
        })->toArray();
    }
}
