<?php

namespace Backpack\MediaLibraryUploaders\Tests\Config\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class UploadersPivot extends Pivot implements HasMedia
{
    use \Backpack\CRUD\app\Models\Traits\CrudTrait;
    use InteractsWithMedia;

    public $timestamps = false;

    public $incrementing = true;

    protected $table = 'uploaders_pivot';

    protected $fillable = ['id', 'uploader_id', 'file_id', 'dropzone', 'easymde', 'upload', 'image', 'upload_multiple'];
}
