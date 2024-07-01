<?php

namespace Backpack\MediaLibraryUploaders\Tests\Config\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class File extends Model implements HasMedia
{
    use \Backpack\CRUD\app\Models\Traits\CrudTrait;
    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    public $timestamps = false;

    public function belongsToManyRelation()
    {
        return $this->belongsToMany(MediaUploader::class, 'media_uploaders_pivot');
    }
}
