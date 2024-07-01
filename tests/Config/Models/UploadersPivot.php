<?php

namespace Backpack\Pro\Tests\Config\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class UploadersPivot extends Pivot implements HasMedia
{
    use \Backpack\CRUD\app\Models\Traits\CrudTrait;
    use InteractsWithMedia;

    public $timestamps = false;

    protected $table = 'uploaders_pivot';
}
