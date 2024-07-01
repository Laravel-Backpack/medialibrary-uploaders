<?php

namespace Backpack\MediaLibraryUploaders\Tests\Config\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MediaUploader extends Model implements HasMedia
{
    use \Backpack\CRUD\app\Models\Traits\CrudTrait;
    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['repeatable'];

    public $timestamps = false;

    protected $casts = [
        'repeatable' => 'json',
    ];

    public function belongsToManyRelation() : BelongsToMany
    {
        return $this->belongsToMany(File::class, 'uploaders_pivot')
                    ->using(UploadersPivot::class)
                    ->withPivot(['dropzone', 'easymde', 'upload', 'image', 'upload_multiple']);
    }

    public function hasManyRelation() : HasMany
    {
        return $this->hasMany(Picture::class, 'uploader_id');
    }

    public function hasOneRelation() : HasOne
    {
        return $this->hasOne(Folder::class, 'uploader_id');
    }
}
