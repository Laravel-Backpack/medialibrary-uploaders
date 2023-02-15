<?php

namespace Backpack\MediaLibraryUploads;

class MediaLibraryEventRegister
{
    public static function handle($field, $mediaDefinition): void
    {
        $attributes = $field->getAttributes();

        $attributes['mediaModel'] = $attributes['model'] ?? get_class($field->crud()->getModel());
        $attributes['mediaName'] = $attributes['name'];

        if (! isset($attributes['subfields'])) {
            $mediaType = self::getUploaderFromFieldType($attributes, $mediaDefinition);
            self::setupModelEvents($attributes['mediaModel'], $mediaType);

            return;
        }

        self::handleRepeatableUploads($attributes, $mediaDefinition);
    }

    private static function setupModelEvents($model, ...$fields): void
    {
        foreach ($fields as $field) {
            $model::saving(function ($entry) use ($field) {
                if (is_a($field, \Backpack\MediaLibraryUploads\RepeatableUploads::class)) {
                    $entry->{$field->fieldName} = json_encode($field->save($entry));
                } else {
                    $field->save($entry);
                    $entry->offsetUnset($field->fieldName);
                }
            });

            $model::retrieved(function ($entry) use ($field) {
                $entry->{$field->fieldName} = $field->getForDisplay($entry);
            });
        }
    }

    public static function handleRepeatableUploads($field, $mediaDefinition)
    {
        $repeatableDefinitions = [];

        foreach ($field['subfields'] as $subfield) {
            if (isset($subfield['withMedia'])) {
                $subfield['mediaModel'] = $subfield['baseModel'] ?? $field['mediaModel'];
                $subfield['mediaName'] = $subfield['name'];

                $subfieldMediaDefinition = $subfield['withMedia'];

                if (is_array($subfieldMediaDefinition)) {
                    $subfieldMediaDefinition = array_merge($mediaDefinition, $subfieldMediaDefinition);
                }

                $mediaType = static::getUploaderFromFieldType($subfield, $subfieldMediaDefinition);

                $repeatableDefinitions[$subfield['mediaModel']][] = $mediaType;
            }
        }

        foreach ($repeatableDefinitions as $model => $mediaTypes) {
            $repeatableDefinition = RepeatableUploads::name($field['name'])->uploads(...$mediaTypes);

            static::setupModelEvents($model, $repeatableDefinition);
        }
    }

    private static function getUploaderFromFieldType($field, $mediaDefinition)
    {
        switch($field['type']) {
            case 'image':
                return ImageField::name($field)->definition($mediaDefinition);
                break;
            case 'upload':
                return UploadField::name($field)->definition($mediaDefinition);
                break;
            case 'upload_multiple':
                return UploadMultipleField::name($field)->definition($mediaDefinition)->multiple();
                break;
            case 'repeatable':
                return RepeatableUploads::name($field)->definition($mediaDefinition);
                break;
            default:
                throw new \Exception('Unknow uploader type for field '.$field['name'].' with type: '.$field['type'].' .');
        }
    }
}
