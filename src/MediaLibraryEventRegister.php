<?php

namespace Backpack\MediaLibraryUploads;

class MediaLibraryEventRegister
{
    public static function handle($field, $mediaDefinition): void
    {
       // dd($field, $mediaDefinition);
        $attributes = $field->getAttributes();

        $model = $attributes['model'] ?? $field->crud()->getModel();
        $model = is_string('model') ? $model : get_class($model);

        if (! isset($attributes['subfields'])) {
            $mediaType = self::getUploaderFromFieldType($attributes, $mediaDefinition);
//dd($mediaType);
            self::setupModelEvents($model, $mediaType);

            return;
        }

        self::handleRepeatableUploads($attributes['name'], $model, $attributes['subfields'], $mediaDefinition);
    }

    private static function setupModelEvents($model, ...$fields): void
    {
       // dD($model, $fields);
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

    public static function handleRepeatableUploads($name, $model, $subfields, $mediaDefinition)
    {
        $repeatableDefinitions = [];

        foreach ($subfields as $subfield) {
            if (isset($subfield['withMedia'])) {
                $model = $subfield['baseModel'] ?? $model;

                $subfieldMediaDefinition = $subfield['withMedia'];

                if (is_array($subfieldMediaDefinition)) {
                    $subfieldMediaDefinition = array_merge($mediaDefinition, $subfieldMediaDefinition);
                }

                $mediaType = static::getUploaderFromFieldType($subfield, $subfieldMediaDefinition);

                $repeatableDefinitions[$model][] = $mediaType;
            }
        }

        foreach ($repeatableDefinitions as $model => $mediaTypes) {
            $repeatableDefinition = RepeatableUploads::name($name)->uploads(...$mediaTypes);

            static::setupModelEvents($model, $repeatableDefinition);
        }
    }

    private static function getUploaderFromFieldType($field, $mediaDefinition)
    {
        switch($field['type']) {
            case 'image':
                return ImageField::name($field['name'])->definition($mediaDefinition);
                break;
            case 'upload':
                return UploadField::name($field['name'])->definition($mediaDefinition);
                break;
            case 'upload_multiple':
                return UploadMultipleField::name($field['name'])->definition($mediaDefinition)->multiple();
                break;
            case 'repeatable':
                return RepeatableUploads::name($field['name'])->definition($mediaDefinition);
                break;
            default:
                throw new \Exception('Unknow uploader type for field '.$field['name'].' with type: '.$field['name'].' .');
        }
    }
}
