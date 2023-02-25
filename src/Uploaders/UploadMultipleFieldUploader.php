<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        return $previousFiles;
    }

    private function saveRepeatableUploadMultiple($entry): void
    {
        $previousFiles = $this->get($entry);

        $filesToDelete = collect($this->getFromRequestAsArray('clear_'))->flatten()->toArray();
        $fileOrder = $this->getFromRequestAsArray('_order_', ',');

        $value = CrudPanelFacade::getRequest()->file($this->parentField) ?? [];

        foreach ($value as $row => $rowValue) {
            foreach ($rowValue[$this->fieldName] ?? [] as $file) {
                if ($file && is_file($file)) {
                    $this->addMediaFile($entry, $file, $row);
                }
            }
        }

        foreach ($previousFiles as $file) {
            if (empty($fileOrder)) {
                $file->delete();

                continue;
            }

            if (in_array($file->getUrl(), $filesToDelete)) {
                $file->delete();

                continue;
            }

            foreach ($fileOrder as $row => $files) {
                if (is_array($files)) {
                    $files = array_map(function ($item) {
                        return Str::after($item, url(''));
                    }, $files);
                    $key = array_search($file->getUrl(), $files, true);

                    if ($key !== false) {
                        $file->order_column = $row;
                        $file->save();
                        // avoid checking the same file twice. This is a performance improvement.
                        unset($fileOrder[$row][$key]);
                    }
                }
                if (empty($fileOrder[$row])) {
                    unset($fileOrder[$row]);
                }
            }
        }
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
