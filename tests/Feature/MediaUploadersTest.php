<?php

namespace Backpack\MediaLibraryUploaders\Tests\Feature;

use Backpack\CRUD\Tests\config\Uploads\HasUploadedFiles;
use Backpack\MediaLibraryUploaders\Tests\Config\Controllers\MediaUploaderCrudController;
use Backpack\MediaLibraryUploaders\Tests\FeatureTestCase;
use Backpack\MediaLibraryUploaders\Tests\Config\Models\MediaUploader;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class MediaUploadersTest extends FeatureTestCase
{
    use HasUploadedFiles;

    protected function defineRoutes($router)
    {
        $router->crud(config('backpack.base.route_prefix').'/media-uploader', MediaUploaderCrudController::class);
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->testBaseUrl = config('backpack.base.route_prefix').'/media-uploader';
    }

    public function test_it_can_access_the_uploaders_create_page()
    {
        $response = $this->get($this->testBaseUrl.'/create');
        $response->assertStatus(200);
    }

    public function test_it_can_upload_a_single_file()
    {
        $response = $this->post($this->testBaseUrl, [
            'upload' => $this->getUploadedFile('avatar1.jpg'),
        ]);

        $response->assertStatus(302);

        $response->assertRedirect($this->testBaseUrl);

        $this->assertDatabaseCount('media_uploaders', 1);

        $uploader = MediaUploader::first();

        $this->assertEquals(1, $uploader->getMedia()->count());

        $this->assertTrue(Storage::disk('uploaders')->exists('1/avatar1.jpg'));
    }

    public function test_it_can_upload_multiple_files()
    {
        $response = $this->post($this->testBaseUrl, [
            'upload_multiple' => [
                $this->getUploadedFile('avatar1.jpg'),
                $this->getUploadedFile('avatar2.jpg'),
            ],
        ]);

        $response->assertStatus(302);

        $response->assertRedirect($this->testBaseUrl);

        $this->assertDatabaseCount('media_uploaders', 1);

        $uploader = MediaUploader::first();

        $this->assertEquals(2, $uploader->getMedia()->count());

        $this->assertTrue(Storage::disk('uploaders')->exists('1/avatar1.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('2/avatar2.jpg'));
    }

    public function test_it_can_upload_files_for_multiple_uploaders()
    {
        $response = $this->post($this->testBaseUrl, [
            'upload' => $this->getUploadedFile('avatar1.jpg'),
            'upload_multiple' => [
                $this->getUploadedFile('avatar2.jpg'),
                $this->getUploadedFile('avatar3.jpg'),
            ],
        ]);

        $response->assertStatus(302);

        $response->assertRedirect($this->testBaseUrl);

        $this->assertDatabaseCount('media_uploaders', 1);

        $uploader = MediaUploader::first();

        $this->assertEquals(3, $uploader->getMedia()->count());

        $this->assertTrue(Storage::disk('uploaders')->exists('1/avatar1.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('2/avatar2.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('3/avatar3.jpg'));
    }

    public function test_it_display_the_edit_page_without_files()
    {
        self::initUploader();

        $response = $this->get($this->testBaseUrl.'/1/edit');
        $response->assertStatus(200);
    }

    /**
     * Undocumented function
     *
     * @group fail
     */
    public function test_it_display_the_upload_page_with_files()
    {
        self::initUploaderWithFiles();
        $response = $this->get($this->testBaseUrl.'/1/edit');

        $response->assertStatus(200);

        $response->assertSee('avatar1.jpg');
        $response->assertSee('avatar2.jpg');
        $response->assertSee('avatar3.jpg');
    }


    private static function initUploader()
    {
        $uploader = new MediaUploader();
        $uploader->save();
    }

    private function initUploaderWithFiles()
    {
        $uploader = new MediaUploader();
        $uploader->addMedia($this->getUploadedFile('avatar1.jpg'))->withCustomProperties([
            'name' => 'upload',
            'repeatableContainerName' => null,
            'repeatableRow' => null,
        ])->preservingOriginal()->toMediaCollection('default', 'uploaders');
        $uploader->addMedia($this->getUploadedFile('avatar2.jpg'))->withCustomProperties([
            'name' => 'upload_multiple',
            'repeatableContainerName' => null,
            'repeatableRow' => null,
        ])->preservingOriginal()->toMediaCollection('default', 'uploaders');
        $uploader->addMedia($this->getUploadedFile('avatar3.jpg'))->withCustomProperties([
            'name' => 'upload_multiple',
            'repeatableContainerName' => null,
            'repeatableRow' => null,
        ])->preservingOriginal()->toMediaCollection('default', 'uploaders');
        $uploader->save();
    }


}
