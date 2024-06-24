<?php

namespace Backpack\MediaLibraryUploaders\Uploaders\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait HandleRepeatableUploads
{
    protected function processRepeatableUploads(Model $entry, Collection $values): array
    {
        foreach (app('UploadersRepository')->getRepeatableUploadersFor($this->getRepeatableContainerName()) as $uploader) {
            $uploader->uploadRepeatableFiles($values->pluck($uploader->getName())->toArray(), $uploader->getPreviousRepeatableMedia($entry), $entry);

            $values->transform(function ($item) use ($uploader) {
                unset($item[$uploader->getName()]);

                return $item;
            });
        }

        return $values->toArray();
    }

    protected function getPreviousRepeatableValues(Model $entry)
    {
        if ($this->canHandleMultipleFiles()) {
            return $this->get($entry)
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

        return $this->get($entry)
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

    protected function getPreviousRepeatableMedia(Model $entry)
    {
        $orderedMedia = [];
        $previousMedia = $this->get($entry)->transform(function ($item) {
            return [$this->getName() => $item, 'order_column' => $item->getCustomProperty('repeatableRow')];
        });
        $previousMedia->each(function ($item) use (&$orderedMedia) {
            $orderedMedia[] = $item[$this->getName()];
        });

        return $orderedMedia;
    }
}