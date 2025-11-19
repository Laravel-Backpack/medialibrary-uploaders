<?php

namespace Backpack\MediaLibraryUploaders\Tests\Config\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use \Illuminate\Database\Eloquent\Relations\HasMany;
use \Illuminate\Database\Eloquent\Relations\BelongsToMany;
use \Illuminate\Database\Eloquent\Relations\HasOne;

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

    protected $table = 'uploaders';

    public $timestamps = false;

    protected $casts = [
        'repeatable' => 'json',
    ];

    public function documents() : BelongsToMany
    {
        return $this->belongsToMany(File::class, 'uploaders_pivot', 'uploader_id', 'file_id')
                    ->using(UploadersPivot::class)
                    ->withPivot(['id', 'dropzone', 'easymde', 'upload', 'image', 'upload_multiple']);
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
