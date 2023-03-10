<?php

namespace Backpack\MediaLibraryUploads;

use Backpack\CRUD\app\Library\CrudPanel\CrudColumn;
use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Exception;

class RegisterUploadEvents
{
    private static $defaultUploaders = [];

    public static function handle($crudObject, $mediaDefinition, $defaultUploaders = []): void
    {
        self::$defaultUploaders = $defaultUploaders;

        $attributes = $crudObject->getAttributes();

        $attributes['eventsModel'] = $attributes['model'] ?? get_class($crudObject->crud()->getModel());

        // we use this check because `MyCustomField extends CrudField` is still a CrudField instance.
        $crudObjectType = is_a($crudObject, CrudField::class) ? CrudField::class : (is_a($crudObject, CrudColumn::class) ? CrudColumn::class : null);

        switch($crudObjectType) {
            case CrudField::class:
                $attributes['crudObjectType'] = 'field';
                break;
            case CrudColumn::class:
                $attributes['crudObjectType'] = 'column';
                break;
            default:
                abort(500, 'Upload handlers only work for CrudField and CrudColumn classes.');
        }

        if (! isset($attributes['subfields'])) {
            $mediaType = self::getUploaderFromField($attributes, $mediaDefinition ?? []);
            self::setupModelEvents($attributes['eventsModel'], $mediaType);

            return;
        }

        self::handleRepeatableUploads($attributes, $mediaDefinition ?? []);
    }

    private static function setupModelEvents($model, $uploader): void
    {
        if ($uploader->crudObjectType === 'field') {
            $model::saving(function ($entry) use ($uploader) {
                $createdModelCount = 'model_count_'.$uploader->name;

                CRUD::set($createdModelCount, CRUD::get($createdModelCount) ?? 0);

                $entry = $uploader->processFileUpload($entry);

                CRUD::set($createdModelCount, CRUD::get($createdModelCount) + 1);
            });
        }

        $model::retrieved(function ($entry) use ($uploader) {
            $entry = $uploader->retrieveUploadedFile($entry);
        });
    }

    private static function handleRepeatableUploads($field, $mediaDefinition)
    {
        $repeatableDefinitions = [];

        foreach ($field['subfields'] as $subfield) {
            if (isset($subfield['withMedia']) || isset($subfield['withUploads'])) {
                $subfield['eventsModel'] = $subfield['baseModel'] ?? $field['eventsModel'];
                $subfield['crudObjectType'] = $field['crudObjectType'];

                $subfieldMediaDefinition = $subfield['withUploads'] ?? $subfield['withMedia'];

                $subfieldMediaDefinition = is_array($subfieldMediaDefinition) ?
                                                array_merge($mediaDefinition, $subfieldMediaDefinition) :
                                                $mediaDefinition;

                $mediaType = static::getUploaderFromField($subfield, $subfieldMediaDefinition);

                $repeatableDefinitions[$subfield['eventsModel']][] = $mediaType;
            }
        }

        foreach ($repeatableDefinitions as $model => $mediaTypes) {
            $repeatableDefinition = self::$defaultUploaders['repeatable']::for($field)->uploads(...$mediaTypes);
            static::setupModelEvents($model, $repeatableDefinition);
        }
    }

    private static function getUploaderFromField($field, $mediaDefinition)
    {
        if (isset($mediaDefinition['uploaderType'])) {
            return $mediaDefinition['uploaderType']::for($field, $mediaDefinition);
        }

        if (isset(self::$defaultUploaders[$field['type']])) {
            return self::$defaultUploaders[$field['type']]::for($field, $mediaDefinition);
        }

        throw new Exception('Undefined upload type for field type: '.$field['type']);
    }
}
