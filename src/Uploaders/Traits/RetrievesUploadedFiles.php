<?php

namespace Backpack\MediaLibraryUploaders\Uploaders\Traits;

use Illuminate\Database\Eloquent\Model;

trait RetrievesUploadedFiles
{
    public $displayConversions = [];

    public function retrieveUploadedFiles(Model $entry): Model
    {
        $media = $this->getPreviousFiles($entry);

        if (! $media) {
            return $entry;
        }

        if (empty($entry->mediaConversions)) {
            $entry->registerAllMediaConversions();
        }

        if ($this->handleRepeatableFiles) {
            $values = $entry->{$this->getRepeatableContainerName()} ?? [];

            if (! is_array($values)) {
                $values = json_decode($values, true);
            }

            $repeatableUploaders = array_merge(app('UploadersRepository')->getRepeatableUploadersFor($this->getRepeatableContainerName()), [$this]);
            foreach ($repeatableUploaders as $uploader) {
                $uploadValues = $uploader->getPreviousRepeatableValues($entry);

                $values = $this->mergeValuesRecursive($values, $uploadValues);
            }

            $entry->{$this->getRepeatableContainerName()} = $values;

            return $entry;
        }

        if (is_a($media, 'Spatie\MediaLibrary\MediaCollections\Models\Media')) {
            $entry->{$this->getName()} = $this->getMediaIdentifier($media, $entry);
        } else {
            $entry->{$this->getName()} = $media->map(function ($item) use ($entry) {
                return $this->getMediaIdentifier($item, $entry);
            })->toArray();
        }

        return $entry;
    }

    public function getPreviousFiles(Model $entry): mixed
    {
        $media = $entry->getMedia($this->collection, function ($media) use ($entry) {
            /** @var Media $media */
            return $media->getCustomProperty('name') === $this->getName() &&
                    $media->getCustomProperty('repeatableContainerName') === $this->repeatableContainerName &&
                    $entry->{$entry->getKeyName()} === $media->getAttribute('model_id');
        });

        if ($this->canHandleMultipleFiles() || $this->handleRepeatableFiles) {
            return $media;
        }

        return $media->first();
    }

    public function getConversionToDisplay($item)
    {
        foreach ($this->displayConversions as $displayConversion) {
            if ($item->hasGeneratedConversion($displayConversion)) {
                return $displayConversion;
            }
        }

        return false;
    }
}