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
    public function __construct(private readonly CrudField|CrudColumn $crudObject, private readonly array $uploadDefinition)
    {
    }

    /**
     * From the given crud object and upload definition provide the event registry
     * service so that uploads are stored and retrieved automatically
     *
     * @param CrudField|CrudColumn $crudObject
     * @param array $uploadDefinition
     * @param array $defaultUploaders
     * @return void
     */
    public static function handle($crudObject, $uploadDefinition): void
    {
        $instance = new self($crudObject, $uploadDefinition);

        $attributes = $crudObject->getAttributes();

        $attributes['entryClass'] = $attributes['model'] ?? get_class($crudObject->crud()->getModel());

        $crudObjectType = is_a($crudObject, CrudField::class) ? 'field' : (is_a($crudObject, CrudColumn::class) ? 'column' : null);

        if(!$crudObjectType) {
                abort(500, 'Upload handlers only work for CrudField and CrudColumn classes.');
        }
      
        $attributes['crudObjectType'] = $crudObjectType;

        if (! isset($attributes['subfields'])) {
            $uploaderType = $instance->getUploader($attributes, $uploadDefinition);
            $instance->setupModelEvents($attributes['entryClass'], $uploaderType);

            return;
        }

        $instance->handleRepeatableUploads($attributes, $uploadDefinition);
    }

    /**
     * Register the saving and retrieved events on model to handle the upload process.
     * In case of CrudColumn we only register the retrieved event.
     *
     * @param string $model
     * @param UploaderInterface|RepeatableUploaderInterface $uploader
     * @return void
     */
    private function setupModelEvents(string $model, UploaderInterface|RepeatableUploaderInterface $uploader): void
    {
        if(app('UploadStore')->isUploadHandled($uploader->getName())) {
            return;
        }

        if ($uploader->getCrudObjectType() === 'field') {
            $model::saving(function ($entry) use ($uploader) {
                $updatedCountKey = 'updated_'.$uploader->getName().'_count';

                CRUD::set($updatedCountKey, CRUD::get($updatedCountKey) ?? 0);

                $entry = $uploader->processFileUpload($entry);

                CRUD::set($updatedCountKey, CRUD::get($updatedCountKey) + 1);
            });
        }

        $model::retrieved(function ($entry) use ($uploader) {
            $entry = $uploader->retrieveUploadedFile($entry);
        });

        app('UploadStore')->markAsHandled($uploader->getName());
    }

    /**
     * Handles the use case when the events need to be setup on subfields (Repeatable fields/columns)
     * We will configure the subfields accordingly before setting up the entry events for uploads.
     *
     * @param array $crudObject
     * @param array $uploadDefinition
     * @return void
     */
    private function handleRepeatableUploads(array $crudObject, array $uploadDefinition)
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

                $uploaderType = $this->getUploader($subfield, $subfielduploadDefinition);

                $repeatableDefinitions[$subfield['entryClass']][] = $uploaderType;
            }
        }

        foreach ($repeatableDefinitions as $model => $uploaderTypes) {
            $repeatableDefinition = app('UploadStore')->getUploadFor('repeatable')::for($crudObject)->uploads(...$uploaderTypes);
            $this->setupModelEvents($model, $repeatableDefinition);
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
     * @return UploaderInterface|RepeatableUploaderInterface
     * @throws Exception
     */
    private function getUploader(array $crudObject, array $uploadDefinition)
    {
        if (isset($uploadDefinition['uploaderType'])) {
            return $uploadDefinition['uploaderType']::for($crudObject, $uploadDefinition);
        }

        if (app('UploadStore')->hasUploadFor($crudObject['type'])) {
            return app('UploadStore')->getUploadFor($crudObject['type'])::for($crudObject, $uploadDefinition);
        }

        throw new Exception('Undefined upload type for '.$crudObject['crudObjectType'].' type: '.$crudObject['type']);
    }
}
