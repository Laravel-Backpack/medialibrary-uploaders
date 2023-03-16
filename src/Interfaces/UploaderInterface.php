<?php

namespace Backpack\MediaLibraryUploads\Interfaces;

use Illuminate\Database\Eloquent\Model;

interface UploaderInterface
{
    public function processFileUpload(Model $entry);

    public function retrieveUploadedFile(Model $entry);

    public function deleteUploadedFile(Model $entry);

    public static function for(array $field, array $configuration);

    public function __construct(array $crudObject, array $configuration);

    public function save(Model $entry, $values = null);

    public function repeats(string $repeatableContainerName): self;

    public function relationship(bool $isRelation): self;

    public function getName();

    public function getDisk();

    public function getPath();

    public function getTemporary();

    public function getExpiration();

    public function getCrudObjectType();
}
