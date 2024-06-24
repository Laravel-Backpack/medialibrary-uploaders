<?php

namespace Backpack\MediaLibraryUploaders\Uploaders;

use Backpack\CRUD\app\Library\Validation\Rules\BackpackCustomRule;
use Backpack\Pro\Uploads\Validation\ValidEasyMDE;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaEasyMDEUploader extends MediaAjaxUploader
{

    public function uploadFiles(Model $entry, $value = null)
    {
        // nothing to do here. The files are uploaded via the EasyMDE editor using the ajax endpoint.
        return $this->isFake() ? ($entry->{$this->getFakeAttribute()}[$this->getAttributeName()]  ?? null) : $entry->{$this->getAttributeName()};
        
    }

    protected function ajaxEndpointSuccessResponse($files = null): \Illuminate\Http\JsonResponse
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($this->getDisk());

        $file = current($files);

        return response()->json([
            'data' => ['filePath' => $disk->url($file)],
        ]);
    }

    protected function getDefaultAjaxEndpointValidation(): BackpackCustomRule
    {
        return ValidEasyMDE::field([])->file(['mimetypes:image/jpeg,image/png,image/jpg', 'max:1024']);
    }
    
    public function uploadRepeatableFiles($values, $previousValues, $entry = null)
    {
        // nothing to do here. The files are uploaded via ajax
        return $values;
    }

    protected function getAjaxEndpointDisk(): \Illuminate\Filesystem\FilesystemAdapter
    {
        return $this->getPermanentDisk();
    }

    protected function getAjaxEndpointPath(): string
    {
        return $this->getPath();
    }

    public function getValueWithoutPath(?string $value = null): ?string
    {
        // don't strip the paths on easyMDE uploads
        return $value;
    }

    public function shouldDeleteFiles(): bool
    {
        return false;
    }
}
