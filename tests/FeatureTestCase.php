<?php 

namespace Backpack\MediaLibraryUploaders\Tests;

use Backpack\CRUD\Tests\config\CrudPanel\BaseDBCrudPanel;
use Backpack\CRUD\Tests\config\Models\User;
use Illuminate\Support\Facades\Storage;

class FeatureTestCase extends BaseDBCrudPanel
{
    protected function getPackageProviders($app)
    {
        $parent = parent::getPackageProviders($app);
        return array_merge($parent, [
            \Spatie\MediaLibrary\MediaLibraryServiceProvider::class,
            \Backpack\MediaLibraryUploaders\AddonServiceProvider::class,
        ]);
    }

    protected function setUp(): void
    { 
        parent::setUp();

        $this->loadMigrationsFrom([
            '--database' => 'testing',
            '--path' => realpath(__DIR__.'/Config/Database/Migrations'),
        ]);
        
        config(['filesystems.disks.uploaders' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ]]);


        Storage::fake('uploaders');

        $this->actingAs(User::find(1));
    }
}