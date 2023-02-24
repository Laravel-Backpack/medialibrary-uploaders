<?php

namespace Backpack\MediaLibraryUploads;

use Backpack\MediaLibraryUploads\Uploaders\MediaRepeatableUploads;
use Exception;

class RegisterUploadEvents
{
    private static $defaultUploaders = [];

    public static function handle($field, $mediaDefinition, $defaultUploaders = []): void
    {
        self::$defaultUploaders = $defaultUploaders;

        $attributes = $field->getAttributes();

        $attributes['eventsModel'] = $attributes['model'] ?? get_class($field->crud()->getModel());

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

        if(isset(self::$defaultUploaders[$field['type']])) {
            return self::$defaultUploaders[$field['type']]::for($field, $mediaDefinition);
        }

        throw new Exception('Undefined upload type for field type: ' . $field['type']);
    }
}
