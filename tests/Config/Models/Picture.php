<?php

namespace Backpack\MeediaLibraryUploaders\Tests\Config\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Picture extends Model implements HasMedia
{
    use \Backpack\CRUD\app\Models\Traits\CrudTrait;
    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['uploader_id', 'upload_multiple', 'upload', 'dropzone', 'easymde', 'image'];

    public $timestamps = false;

    public function belongsToRelation()
    {
        return $this->belongsTo(MediaUploader::class);
    }
}
