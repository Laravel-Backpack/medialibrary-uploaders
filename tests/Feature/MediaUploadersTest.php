<?php

namespace Backpack\MediaLibraryUploaders\Tests\Feature;

use Backpack\CRUD\Tests\config\Uploads\HasUploadedFiles;
use Backpack\MediaLibraryUploaders\Tests\Config\Controllers\MediaUploaderCrudController;
use Backpack\MediaLibraryUploaders\Tests\Config\Models\MediaUploader;
use Backpack\MediaLibraryUploaders\Tests\FeatureTestCase;
use Illuminate\Support\Facades\Storage;

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

        $this->assertDatabaseCount('uploaders', 1);

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

        $this->assertDatabaseCount('uploaders', 1);

        $uploader = MediaUploader::first();

        $this->assertEquals(2, $uploader->getMedia()->count());

        $this->assertTrue(Storage::disk('uploaders')->exists('1/avatar1.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('2/avatar2.jpg'));
    }

    public function test_it_can_upload_files_for_multiple_uploaders()
    {
        $response = $this->post($this->testBaseUrl, [
            'upload'          => $this->getUploadedFile('avatar1.jpg'),
            'upload_multiple' => [
                $this->getUploadedFile('avatar2.jpg'),
                $this->getUploadedFile('avatar3.jpg'),
            ],
        ]);

        $response->assertStatus(302);

        $response->assertRedirect($this->testBaseUrl);

        $this->assertDatabaseCount('uploaders', 1);

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

    public function test_it_display_the_upload_page_with_files()
    {
        self::initUploaderWithFiles();
        $response = $this->get($this->testBaseUrl.'/1/edit');

        $response->assertStatus(200);

        $response->assertSee('avatar1.jpg');
        $response->assertSee('avatar2.jpg');
        $response->assertSee('avatar3.jpg');
    }

    public function test_it_can_update_files()
    {
        self::initUploaderWithFiles();

        $response = $this->put($this->testBaseUrl.'/1', [
            'upload'          => $this->getUploadedFile('avatar4.jpg'),
            'upload_multiple' => [
                $this->getUploadedFile('avatar5.jpg'),
                $this->getUploadedFile('avatar6.jpg'),
            ],
            'clear_upload_multiple' => ['2/avatar2.jpg',  '3/avatar3.jpg'],
            'id'                    => 1,
        ]);

        $response->assertStatus(302);

        $response->assertRedirect($this->testBaseUrl);

        $this->assertDatabaseCount('uploaders', 1);

        $uploader = MediaUploader::first();

        $this->assertEquals(3, $uploader->getMedia()->count());

        $this->assertTrue(Storage::disk('uploaders')->exists('4/avatar4.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('5/avatar5.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('6/avatar6.jpg'));
        $this->assertFalse(Storage::disk('uploaders')->exists('2/avatar2.jpg'));
        $this->assertFalse(Storage::disk('uploaders')->exists('3/avatar3.jpg'));
        $this->assertFalse(Storage::disk('uploaders')->exists('1/avatar1.jpg'));

    }

    public function test_it_keeps_previous_values_unchanged_when_not_deleted()
    {
        self::initUploaderWithFiles();

        $response = $this->put($this->testBaseUrl.'/1', [
            'upload_multiple' => ['2/avatar2.jpg', '3/avatar3.jpg'],
            'id'                    => 1,
        ]);

        $response->assertStatus(302);

        $response->assertRedirect($this->testBaseUrl);

        $this->assertDatabaseCount('uploaders', 1);

        $uploader = MediaUploader::first();

        $this->assertEquals(3, $uploader->getMedia()->count());

        $this->assertTrue(Storage::disk('uploaders')->exists('1/avatar1.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('2/avatar2.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('3/avatar3.jpg'));
    }

    public function test_upload_multiple_can_delete_uploaded_files_and_add_at_the_same_time()
    {
        self::initUploaderWithFiles();

        $response = $this->put($this->testBaseUrl.'/1', [
            'upload_multiple' => $this->getUploadedFiles(['avatar4.jpg', 'avatar5.jpg']),
            'clear_upload_multiple' => ['2/avatar2.jpg'],
            'id' => 1,
        ]);

        $response->assertStatus(302);

        $response->assertRedirect($this->testBaseUrl);

        $this->assertDatabaseCount('uploaders', 1);

        $uploader = MediaUploader::first();

        $this->assertEquals(4, $uploader->getMedia()->count());

        $this->assertTrue(Storage::disk('uploaders')->exists('1/avatar1.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('3/avatar3.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('4/avatar4.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('5/avatar5.jpg'));

    }

    public function test_it_can_delete_uploaded_files()
    {
        self::initUploaderWithFiles();

        $response = $this->delete($this->testBaseUrl.'/1');

        $response->assertStatus(200);

        $this->assertDatabaseCount('uploaders', 0);

        $files = Storage::disk('uploaders')->allFiles();

        $this->assertEquals(0, count($files));
    }

    public function test_it_does_not_upload_files_with_extensions_that_are_not_allowed()
    {
        $response = $this->post($this->testBaseUrl, [
            'upload'          => $this->getFileWithContent('payload.html', 'just some text'),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('upload');

        $response = $this->post($this->testBaseUrl, [
            'upload_multiple' => [
                $this->getUploadedFile('avatar1.jpg'),
                $this->getFileWithContent('payload.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            ],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('upload_multiple');

        $this->assertDatabaseCount('uploaders', 0);
        $this->assertDatabaseCount('media', 0);
        $this->assertEmpty(Storage::disk('uploaders')->allFiles());
    }

    public function test_it_keeps_previous_files_when_the_new_file_is_not_allowed()
    {
        self::initUploaderWithFiles();

        $response = $this->put($this->testBaseUrl.'/1', [
            'upload' => $this->getFileWithContent('payload.svg', 'just some text'),
            'id'     => 1,
        ]);

        $response->assertSessionHasErrors('upload');

        $response = $this->put($this->testBaseUrl.'/1', [
            'upload_multiple'       => [$this->getFileWithContent('payload.html', 'just some text')],
            'clear_upload_multiple' => ['2/avatar2.jpg'],
            'id'                    => 1,
        ]);

        $response->assertSessionHasErrors('upload_multiple');

        $this->assertEquals(3, MediaUploader::first()->getMedia()->count());
        $this->assertTrue(Storage::disk('uploaders')->exists('1/avatar1.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('2/avatar2.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('3/avatar3.jpg'));
    }

    public function test_it_can_upload_a_base64_image()
    {
        $response = $this->post($this->testBaseUrl.'/base64-image', [
            'image' => $this->getBase64Image(),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $this->assertEquals(1, MediaUploader::first()->getMedia()->count());
        $this->assertStringEndsWith('.jpeg', MediaUploader::first()->getMedia()->first()->file_name);
    }

    public static function invalidBase64Images(): array
    {
        $svg = base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.domain)</script></svg>');
        $html = base64_encode('<!DOCTYPE html><html><body><script>alert(document.domain)</script></body></html>');

        return [
            'svg'                => ['data:image/svg+xml;base64,'.$svg],
            'html declared as image' => ['data:image/html;base64,'.$html],
            'svg declared as jpeg'   => ['data:image/jpeg;base64,'.$svg],
            'invalid base64'         => ['data:image/png;base64,not-base64!'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidBase64Images')]
    public function test_it_does_not_upload_base64_images_that_are_not_valid(string $image)
    {
        $response = $this->post($this->testBaseUrl.'/base64-image', [
            'image' => $image,
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseCount('media', 0);
        $this->assertEmpty(Storage::disk('uploaders')->allFiles());
    }

    private function getFileWithContent(string $clientName, string $content): \Illuminate\Http\UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'backpack-upload');
        file_put_contents($path, $content);

        return new \Illuminate\Http\UploadedFile($path, $clientName, null, null, true);
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
            'name'                    => 'upload',
            'repeatableContainerName' => null,
            'repeatableRow'           => null,
        ])->preservingOriginal()->toMediaCollection('default', 'uploaders');
        $uploader->addMedia($this->getUploadedFile('avatar2.jpg'))->withCustomProperties([
            'name'                    => 'upload_multiple',
            'repeatableContainerName' => null,
            'repeatableRow'           => null,
        ])->preservingOriginal()->toMediaCollection('default', 'uploaders');
        $uploader->addMedia($this->getUploadedFile('avatar3.jpg'))->withCustomProperties([
            'name'                    => 'upload_multiple',
            'repeatableContainerName' => null,
            'repeatableRow'           => null,
        ])->preservingOriginal()->toMediaCollection('default', 'uploaders');
        $uploader->save();
    }
}
