<?php

namespace Backpack\MediaLibraryUploaders\Uploaders\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait HandleRepeatableUploads
{
    public function processRepeatableUploads(Model $entry, Collection $values): Collection
    {
        foreach (app('UploadersRepository')->getRepeatableUploadersFor($this->getRepeatableContainerName()) as $uploader) {
            $uploader->uploadRepeatableFiles($values->pluck($uploader->getName())->toArray(), $uploader->getPreviousRepeatableMedia($entry), $entry);

            $values->transform(function ($item) use ($uploader) {
                unset($item[$uploader->getName()]);

                return $item;
            });
        }

        return $values;
    }

    protected function uploadRelationshipFiles(Model $entry): Model
    {
        $entryValue = $entry->{$this->getAttributeName()};

        if ($this->handleMultipleFiles && is_string($entryValue)) {
            try {
                $entryValue = json_decode($entryValue, true);
            } catch (\Exception) {
                return $entry;
            }
        }

        if ($this->hasDeletedFiles($entryValue)) {
            $entry->{$this->getAttributeName()} = $this->uploadFiles($entry, false);
            $this->updatedPreviousFiles = $this->getEntryAttributeValue($entry);
        }

        if ($this->shouldKeepPreviousValueUnchanged($entry, $entryValue)) {
            $entry->{$this->getAttributeName()} = $this->updatedPreviousFiles ?? $this->getEntryOriginalValue($entry);

            return $entry;
        }

        if ($this->shouldUploadFiles($entryValue)) {
            $entry->{$this->getAttributeName()} = $this->uploadFiles($entry, $entryValue);
        }

        return $entry;
    }

    public function getPreviousRepeatableValues(Model $entry)
    {
        if ($this->canHandleMultipleFiles()) {
            return $this->getPreviousFiles($entry)
                        ->groupBy(function ($item) {
                            return $item->getCustomProperty('repeatableRow');
                        })
                        ->transform(function ($media) use ($entry) {
                            $mediaItems = $media->map(function ($item) use ($entry) {
                                return $this->getMediaIdentifier($item, $entry);
                            })
                            ->toArray();

                            return [$this->getName() => $mediaItems];
                        })
                        ->toArray();
        }

        return $this->getPreviousFiles($entry)
                    ->transform(function ($item) use ($entry) {
                        return [
                            $this->getName() => $this->getMediaIdentifier($item, $entry),
                            'order_column'   => $item->getCustomProperty('repeatableRow'),
                        ];
                    })
                    ->sortBy('order_column')
                    ->keyBy('order_column')
                    ->toArray();
    }

    public function getPreviousRepeatableMedia(Model $entry)
    {
        $orderedMedia = [];
        $previousMedia = $this->getPreviousFiles($entry)->transform(function ($item) {
            return [$this->getName() => $item, 'order_column' => $item->getCustomProperty('repeatableRow')];
        });
        $previousMedia->each(function ($item) use (&$orderedMedia) {
            $orderedMedia[] = $item[$this->getName()];
        });

        return $orderedMedia;
    }
}
