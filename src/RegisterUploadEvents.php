<?php

namespace Backpack\MediaLibraryUploads;

use Backpack\CRUD\app\Library\CrudPanel\CrudColumn;
use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\MediaLibraryUploads\Interfaces\RepeatableUploaderInterface;
use Backpack\MediaLibraryUploads\Interfaces\UploaderInterface;
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
            $uploaderType = self::getUploader($attributes, $uploadDefinition ?? []);
            self::setupModelEvents($attributes['entryClass'], $uploaderType);

            return;
        }

        self::handleRepeatableUploads($attributes, $uploadDefinition ?? []);
    }

    /**
     * Register the saving and retrieved events on model to handle the upload process.
     * In case of CrudColumn we only register the retrieved event. 
     *
     * @param string $model
     * @param UploaderInterface|RepeatableUploaderInterface $uploader
     * @return void
     */
    private static function setupModelEvents(string $model, UploaderInterface|RepeatableUploaderInterface $uploader): void
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

    /**
     * Handles the use case when the events need to be setup on subfields (Repeatable fields/columns)
     * We will configure the subfields accordingly before setting up the entry events for uploads.
     *
     * @param array $crudObject
     * @param array $uploadDefinition
     * @return void
     */
    private static function handleRepeatableUploads(array $crudObject, array $uploadDefinition)
    {
        $repeatableDefinitions = [];

        foreach ($crudObject['subfields'] as $subfield) {
            if (isset($subfield['withMedia']) || isset($subfield['withUploads'])) {
                $subfield['entryClass'] = $subfield['baseModel'] ?? $crudObject['entryClass'];
                $subfield['crudObjectType'] = $crudObject['crudObjectType'];

                $subfielduploadDefinition = $subfield['withUploads'] ?? $subfield['withMedia'];

                $subfielduploadDefinition = is_array($subfielduploadDefinition) ?
                                                array_merge($uploadDefinition, $subfielduploadDefinition) :
                                                $uploadDefinition;

                $uploaderType = static::getUploader($subfield, $subfielduploadDefinition);

                $repeatableDefinitions[$subfield['entryClass']][] = $uploaderType;
            }
        }

        foreach ($repeatableDefinitions as $model => $uploaderTypes) {
            $repeatableDefinition = self::$defaultUploaders['repeatable']::for($crudObject)->uploads(...$uploaderTypes);
            static::setupModelEvents($model, $repeatableDefinition);
        }
    }

    /**
     * Return the uploader for the object beeing configured.
     * We will give priority to any uploader provided by `uploader => App\SomeUploaderClass` on upload definition.
     * 
     * If none provided, we will use the Backpack defaults for the given object type.
     * 
     * Throws an exception in case no uploader for the given object type is found.
     *
     * @param array $crudObject
     * @param array $uploadDefinition
     * @return UploaderInterface|ReatableUploaderInterface
     * @throws Exception
     */
    private static function getUploader(array $crudObject, array $uploadDefinition)
    {
        if (isset($uploadDefinition['uploaderType'])) {
            return $uploadDefinition['uploaderType']::for($crudObject, $uploadDefinition);
        }

        if (isset(self::$defaultUploaders[$crudObject['type']])) {
            return self::$defaultUploaders[$crudObject['type']]::for($crudObject, $uploadDefinition);
        }

        throw new Exception('Undefined upload type for '.$crudObject['crudObjectType'].' type: '.$crudObject['type']);
    }
}
