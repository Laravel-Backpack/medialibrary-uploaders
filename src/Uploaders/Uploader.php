<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudColumn;
use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\MediaLibraryUploads\Interfaces\UploaderInterface;
use Backpack\MediaLibraryUploads\Traits\HasCrudObjectType;
use Backpack\MediaLibraryUploads\Traits\HasName;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class Uploader implements UploaderInterface
{
    use HasCrudObjectType, HasName;

    /**
     * Indicates the uploaded file should be deleted when entry is deleted
     *
     * @var bool
     */
    public $deleteWhenEntryIsDeleted = true;

    /**
     * Indicates if this uploader instance is inside a repeatable container
     *
     * @var bool
     */
    public $isRepeatable = false;

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
     * @var bool
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
     * @var bool
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
     * @var bool
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
        $this->path = empty($this->path) ? $this->path : Str::of($this->path)->finish('/')->value;
        $this->crudObjectType = $crudObject['crudObjectType'];
        $this->fileName = $configuration['fileName'] ?? $this->fileName;
        $this->deleteWhenEntryIsDeleted = $configuration['whenDelete'] ?? $this->deleteWhenEntryIsDeleted;
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
     * @return bool
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
        $value = $entry->{$this->name};

        if ($this->isMultiple && ! isset($entry->getCasts()[$this->name]) && is_string($value)) {
            $entry->{$this->name} = json_decode($value, true);
        } else {
            $entry->{$this->name} = Str::after($value, $this->path);
        }

        return $entry;
    }

    /**
     * The function called in the deleting event to delete the uploaded files upon entry deletion
     *
     * @param Model $entry
     * @return void
     */
    public function deleteUploadedFile(Model $entry)
    {
        $values = $entry->{$this->name};

        if ($this->isMultiple) {
            if (! isset($entry->getCasts()[$this->name]) && is_string($values)) {
                $values = json_decode($values, true);
            }
        } else {
            $values = (array) Str::after($values, $this->path);
        }

        foreach ($values as $value) {
            Storage::disk($this->disk)->delete($this->path.$value);
        }
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
     * @param bool $isRelationship
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
        if (is_callable($this->fileName)) {
            return ($this->fileName)($file, $this);
        }

        return $this->fileName;
    }
}
