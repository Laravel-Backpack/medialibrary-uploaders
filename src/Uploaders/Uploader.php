<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\MediaLibraryUploads\Interfaces\UploaderInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Closure;
use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\CRUD\app\Library\CrudPanel\CrudColumn;
use Backpack\MediaLibraryUploads\Traits\HasCrudObjectType;

abstract class Uploader implements UploaderInterface
{
    use HasCrudObjectType;
    
    /**
     * Indicates if this uploader instance is inside a repeatable container
     *
     * @var boolean
     */
    public $isRepeatable = false;

    /**
     * The name of the uploader AKA CrudField/Column name.
     *
     * @var string
     */
    public $name;

    /**
     * When inside a repeatable container, indicates the container name
     *
     * @var string|null
     */
    public $repeatableContainerName = null;

    /**
     * Developer provided filename
     *
     * @var null|string|Closure
     */
    public $fileName = null;

    /**
     * The disk where upload will be stored. By default `public`
     *
     * @var string
     */
    public $disk = 'public';

    /**
     * Indicates if the upload handles multiple files
     *
     * @var boolean
     */
    public $isMultiple = false;

    /**
     * The class of the entry where uploads will be attached to
     *
     * @var string
     */
    public $entryClass;

    /**
     * The path inside the disk to store the uploads
     *
     * @var string
     */
    public $path = '';

    /**
     * Should the url to the object be a temporary one (eg: s3)
     *
     * @var boolean
     */
    public $temporary = false;

    /**
     * When using temporary urls, defines the time that the url 
     * should be available in minutes.
     * 
     * By default 1 minute
     *
     * @var int
     */
    public $expiration = 1;

    /**
     * Indicates if the upload is relative to a relationship field/column
     *
     * @var boolean
     */
    public $isRelationship = false;

    public function __construct(array $crudObject, array $configuration)
    {
        $this->name = $crudObject['name'];
        $this->disk = $configuration['disk'] ?? $crudObject['disk'] ?? $this->disk;
        $this->temporary = $configuration['temporary'] ?? $this->temporary;
        $this->expiration = $configuration['expiration'] ?? $this->expiration;
        $this->entryClass = $crudObject['entryClass'];
        $this->path = $configuration['path'] ?? $crudObject['prefix'] ?? $this->path;
        $this->path = empty($this->path) ? $this->path : Str::of($this->path)->finish('/');
        $this->crudObjectType = $crudObject['crudObjectType'];
        $this->fileName = $configuration['fileName'] ?? $this->fileName;
    }

    /**
     * An abstract function that all uploaders must implement with the saving process. 
     *
     * @param Model $entry
     * @param mixed $values
     * @return mixed
     */
    abstract public function save(Model $entry, $values = null);

    /**
     * The function called in the saving event that starts the upload process.
     *
     * @param Model $entry
     * @return Model
     */
    public function processFileUpload(Model $entry)
    {
        $entry->{$this->name} = $this->save($entry);

        return $entry;
    }

    /**
     * Return the uploader name
     *
     * @return string
     */
    public function getName() 
    {
        return $this->name;
    }

    /**
     * Return the uploader disk
     *
     * @return string
     */
    public function getDisk()
    {
        return $this->disk;
    }

    /**
     * Return the uploader path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Return the uploader temporary option
     *
     * @return boolean
     */
    public function getTemporary()
    {
        return $this->temporary;
    }

    /**
     * Return the uploader expiration time in minutes
     *
     * @return int
     */
    public function getExpiration()
    {
        return $this->expiration;
    }


    /**
     * The function called in the retrieved event that handles the display of uploaded values
     *
     * @param Model $entry
     * @return Model
     */
    public function retrieveUploadedFile(Model $entry)
    {
        $this->setupUploadConfigsInCrudObject(CRUD::{$this->crudObjectType}($this->name));

        $value = $entry->{$this->name};

        if ($this->isMultiple && ! isset($entry->getCasts()[$this->name]) && is_string($value)) {
            $entry->{$this->name} = json_decode($value, true);
        } else {
            $entry->{$this->name} = Str::after($value, $this->path);
        }

        return $entry;
    }

    /**
     * Build an uploader instance. 
     *
     * @param array $crudObject
     * @param array $definition
     * @return self
     */
    public static function for(array $crudObject, array $definition)
    {
        return new static($crudObject, $definition);
    }

    /**
     * Set multiple attribute to true in the uploader.
     *
     * @return self
     */
    protected function multiple()
    {
        $this->isMultiple = true;

        return $this;
    }


    /**
     * Set relationship attribute in uploader. 
     * When true, it also removes the repeatable in case the relationship is handled 
     * by repeatable interface. 
     * This is because the uploads are only repeatable on the "main model", but they represent
     * one entry per row. (not repeatable in the "relationship model")
     *
     * @param boolean $isRelationship
     * @return self
     */
    public function relationship(bool $isRelationship): self
    {
        if ($isRelationship) {
            $this->isRepeatable = false;
        }
        $this->isRelationship = $isRelationship;

        return $this;
    }

    /**
     * Set the repeatable attribute to true in the uploader and the 
     * corresponding container name.
     *
     * @param string $repeatableContainerName
     * @return self
     */
    public function repeats(string $repeatableContainerName): self
    {
        $this->isRepeatable = true;

        $this->repeatableContainerName = $repeatableContainerName;

        return $this;
    }

    /**
     * Repeatable items send _order_ parameter in the request. 
     * This olds the information for uploads inside repeatable containers.
     *
     * @return array
     */
    protected function getFileOrderFromRequest()
    {
        $items = CRUD::getRequest()->input('_order_'.$this->repeatableContainerName) ?? [];

        array_walk($items, function (&$key, $value) {
            $requestValue = $key[$this->name] ?? null;
            $key = $this->isMultiple ? (is_string($requestValue) ? explode(',', $requestValue) : $requestValue) : $requestValue;
        });

        return $items;
    }

    /**
     * Return a new instance of the entry class for the uploader
     *
     * @return Model
     */
    protected function modelInstance()
    {
        return new $this->entryClass;
    }

    /**
     * Return the uploader stored values when in a repeatable container
     *
     * @param Model $entry
     * @return array
     */
    protected function getPreviousRepeatableValues(Model $entry)
    {
        $previousValues = json_decode($entry->getOriginal($this->repeatableContainerName), true);
        if (! empty($previousValues)) {
            $previousValues = array_column($previousValues, $this->name);
        }

        return $previousValues ?? [];
    }

    /**
     * Return the file extension
     *
     * @param mixed $file
     * @return string
     */
    protected function getExtensionFromFile($file)
    {
        return is_a($file, UploadedFile::class, true) ? $file->extension() : Str::after(mime_content_type($file), '/');
    }

    /**
     * Return the file name built by Backpack or by the developer in `fileName` configuration.
     *
     * @param mixed $file
     * @return string
     */
    protected function getFileName($file)
    {
        if (is_file($file)) {
            return Str::of($this->fileNameFrom($file) ?? Str::of($file->getClientOriginalName())->beforeLast('.')->slug()->append('-'.Str::random(4)));
        }

        return Str::of($this->fileNameFrom($file) ?? Str::random(40));
    }

    /**
     * Return the complete filename and extension
     *
     * @param mixed $file
     * @return string
     */
    protected function getFileNameWithExtension($file)
    {
        if (is_file($file)) {
            return $this->getFileName($file).'.'.$this->getExtensionFromFile($file);
        }

        return Str::of($this->fileNameFrom($file) ?? Str::random(40)).'.'.$this->getExtensionFromFile($file);
    }

    /**
     * Allow developer to override the default Backpack fileName
     *
     * @param mixed $file
     * @return string|null
     */
    private function fileNameFrom($file) 
    {
        if(is_callable($this->fileName)) {
            return ($this->fileName)($file, $this);
        }

        return $this->fileName;
    }

    /**
     * Set up the upload attributes in the field/column
     * 
     * @param CrudField|CrudColumn $crudObject
     * @return void
     */
    protected function setupUploadConfigsInCrudObject(CrudField|CrudColumn $crudObject)
    {
        $attributes = $crudObject->getAttributes();
        $crudObject->upload(true)
            ->disk($attributes['disk'] ?? $this->disk)
            ->prefix($attributes['prefix'] ?? $this->path);
    }
}
