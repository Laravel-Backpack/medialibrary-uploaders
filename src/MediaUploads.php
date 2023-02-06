<?php

namespace Backpack\MediaLibraryUploads;

class MediaUploads
{
    private static $model;

    public static function handle($model, ...$fields): void
    {
        self::$model = !is_string($model) ? get_class($model) : $model;
        if (! empty($fields)) {
            self::setupModelEvents(...$fields);
        }
    }

    private static function setupModelEvents(...$fields): void
    {
        foreach ($fields as $field) {
            self::$model::saving(function ($entry) use ($field) {
                if (is_a($field, \Backpack\MediaLibraryUploads\RepeatableUploads::class)) {
                    $entry->{$field->fieldName} = json_encode($field->save($entry));
                } else {
                    $field->save($entry);
                    $entry->offsetUnset($field->fieldName);
                }
            });

            self::$model::retrieved(function ($entry) use ($field) {
                $entry->{$field->fieldName} = $field->getForDisplay($entry);
            });
        }
    }

    public static function handleRepeatableUploads($field, $saveCallback = null, $getCallback = null)
    {
        $attributes = $field->getAttributes();
        $repeatableDefinitions = [];
        
        foreach ($attributes['subfields'] as $repeatableField) {

            if (isset($repeatableField['withMedia'])) {

                $model = $repeatableField['baseModel'] ?? $field->crud()->getModel();
                $model = is_string($model) ? $model : get_class($model);
                
                $callable = $repeatableField['withMedia'];
                $mediaType = static::getUploaderFromFieldType($repeatableField);

                if (is_callable($saveCallback)) {
                    $mediaType = $mediaType->saveCallback($callable);
                }

                if(is_callable($getCallback)) {
                    $mediaType = $mediaType->getCallback($callable);
                }

                $repeatableDefinitions[$model][] = $mediaType;
            }
        }
    
        foreach($repeatableDefinitions as $model => $definitions) {

            $mediaDefinition = RepeatableUploads::name($attributes['name'])->uploads(...$definitions);

            static::handle($model, $mediaDefinition);

        }
    }

    public static function handleUploads($field, $saveCallback = null, $getCallback = null)
    {
        $attributes = $field->getAttributes();
        $model = $attributes['baseModel'] ?? $field->crud()->getModel();
        $mediaType = static::getUploaderFromFieldType($attributes);

        if ( $saveCallback && is_callable($saveCallback)) {  
            $mediaType = $mediaType->saveCallback($saveCallback);
        }

        if($getCallback && is_callable($getCallback)) {
            $mediaType = $mediaType->getCallback($getCallback);
        }

        static::handle($model, $mediaType);
    }

    private static function getUploaderFromFieldType($field)
    {
        switch($field['type']) {
            case 'image':
                return ImageField::name($field['name']);
                break;
            case 'upload':
                return UploadField::name($field['name']);
                break;
            case 'upload_multiple':
                return UploadMultipleField::name($field['name'])->multiple();
                break;
            case 'repeatable':
                return RepeatableUploads::name($field['name']);
                break;
            default:
                throw new \Exception('Unknow uploader type for field ' . $field['name'] . ' with type: '.$field['name'].' .');
        }
    }
}
