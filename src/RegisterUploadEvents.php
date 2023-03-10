<?php

namespace Backpack\MediaLibraryUploads;

use Backpack\CRUD\app\Library\CrudPanel\CrudColumn;
use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Exception;

class RegisterUploadEvents
{
    private static $defaultUploaders = [];

    /**
     * From the given crud object and upload definition provide the event registry
     * service so that uploads are stored and retrieved automatically
     *
     * @param CrudField|CrudColumn $crudObject
     * @param array $uploadDefinition
     * @param array $defaultUploaders
     * @return void
     */
    public static function handle($crudObject, $uploadDefinition, $defaultUploaders = []): void
    {
        self::$defaultUploaders = $defaultUploaders;

        $attributes = $crudObject->getAttributes();

        $attributes['entryClass'] = $attributes['model'] ?? get_class($crudObject->crud()->getModel());

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
            $mediaType = self::getUploaderFromField($attributes, $uploadDefinition ?? []);
            self::setupModelEvents($attributes['entryClass'], $mediaType);

            return;
        }

        self::handleRepeatableUploads($attributes, $uploadDefinition ?? []);
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

    private static function handleRepeatableUploads($field, $uploadDefinition)
    {
        $repeatableDefinitions = [];

        foreach ($field['subfields'] as $subfield) {
            if (isset($subfield['withMedia']) || isset($subfield['withUploads'])) {
                $subfield['entryClass'] = $subfield['baseModel'] ?? $field['entryClass'];
                $subfield['crudObjectType'] = $field['crudObjectType'];

                $subfielduploadDefinition = $subfield['withUploads'] ?? $subfield['withMedia'];

                $subfielduploadDefinition = is_array($subfielduploadDefinition) ?
                                                array_merge($uploadDefinition, $subfielduploadDefinition) :
                                                $uploadDefinition;

                $mediaType = static::getUploaderFromField($subfield, $subfielduploadDefinition);

                $repeatableDefinitions[$subfield['entryClass']][] = $mediaType;
            }
        }

        foreach ($repeatableDefinitions as $model => $mediaTypes) {
            $repeatableDefinition = self::$defaultUploaders['repeatable']::for($field)->uploads(...$mediaTypes);
            static::setupModelEvents($model, $repeatableDefinition);
        }
    }

    private static function getUploaderFromField($field, $uploadDefinition)
    {
        if (isset($uploadDefinition['uploaderType'])) {
            return $uploadDefinition['uploaderType']::for($field, $uploadDefinition);
        }

        if (isset(self::$defaultUploaders[$field['type']])) {
            return self::$defaultUploaders[$field['type']]::for($field, $uploadDefinition);
        }

        throw new Exception('Undefined upload type for field type: '.$field['type']);
    }
}
