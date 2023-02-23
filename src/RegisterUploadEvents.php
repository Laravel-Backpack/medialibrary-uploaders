<?php

namespace Backpack\MediaLibraryUploads;

use Backpack\MediaLibraryUploads\Uploaders\MediaImageFieldUploader;
use Backpack\MediaLibraryUploads\Uploaders\MediaRepeatableUploads;
use Backpack\MediaLibraryUploads\Uploaders\MediaUploadFieldUploader;
use Backpack\MediaLibraryUploads\Uploaders\MediaUploadMultipleFieldUploader;

class RegisterUploadEvents
{
    public static function handle($field, $mediaDefinition): void
    {
        $attributes = $field->getAttributes();

        $attributes['eventsModel'] = $attributes['model'] ?? get_class($field->crud()->getModel());
        $attributes['mediaName'] = $attributes['name'];

        if (! isset($attributes['subfields'])) {
            $mediaType = self::getUploaderFromField($attributes, $mediaDefinition ?? []);
            self::setupModelEvents($attributes['eventsModel'], $mediaType);

            return;
        }

        self::handleRepeatableUploads($attributes, $mediaDefinition ?? []);
    }

    private static function setupModelEvents($model, ...$fields): void
    {
        foreach ($fields as $field) {
            $model::saving(function ($entry) use ($field) {
                $entry = $field->getSavingEvent($entry);
            });

            $model::retrieved(function ($entry) use ($field) {
                $entry = $field->getRetrievedEvent($entry);
            });
        }
    }

    private static function handleRepeatableUploads($field, $mediaDefinition)
    {
        $repeatableDefinitions = [];

        foreach ($field['subfields'] as $subfield) {
            if (isset($subfield['withMedia'])) {
                $subfield['eventsModel'] = $subfield['baseModel'] ?? $field['eventsModel'];
                $subfield['mediaName'] = $subfield['name'];

                $subfieldMediaDefinition = $subfield['withMedia'];

                $subfieldMediaDefinition = is_array($subfieldMediaDefinition) ?
                                                array_merge($mediaDefinition, $subfieldMediaDefinition) :
                                                $mediaDefinition;
    
                $mediaType = static::getUploaderFromField($subfield, $subfieldMediaDefinition);

                $repeatableDefinitions[$subfield['eventsModel']][] = $mediaType;
            }
        }

        foreach ($repeatableDefinitions as $model => $mediaTypes) {
            $repeatableDefinition = MediaRepeatableUploads::for($field, [])->uploads(...$mediaTypes);

            static::setupModelEvents($model, $repeatableDefinition);
        }
    }

    private static function getUploaderFromField($field, $mediaDefinition)
    {
        if (isset($mediaDefinition['uploaderType'])) {
            return $mediaDefinition['uploaderType']::for($field, $mediaDefinition);
        }

        switch($field['type']) {
            case 'image':
                return MediaImageFieldUploader::for($field, $mediaDefinition);
                break;
            case 'upload':
                return MediaUploadFieldUploader::for($field, $mediaDefinition);
                break;
            case 'upload_multiple':
                return MediaUploadMultipleFieldUploader::for($field, $mediaDefinition);
                break;
            case 'repeatable':
                return MediaRepeatableUploads::for($field, $mediaDefinition);
                break;
            default:
                throw new \Exception('Unknow uploader type for field '.$field['name'].' with type: '.$field['type'].' .');
        }
    }
}
