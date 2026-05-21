<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attachments\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function indexForTask(Task $task, Request $request)
    {
        $this->authorize('viewAny', [Attachment::class, $task]);

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $attachments = $task->attachments()
            ->with('uploader')
            ->latest()
            ->paginate($perPage);

        return AttachmentResource::collection($attachments);
    }

    public function storeForTask(StoreAttachmentRequest $request, Task $task): AttachmentResource
    {
        $this->authorize('create', [Attachment::class, $task]);

        $file = $request->file('file');

        $path = $file->store('attachments/' . $task->id);

        $attachment = Attachment::create([
            'task_id' => $task->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
        ]);

        $attachment->load('uploader');

        return new AttachmentResource($attachment);
    }

    public function download(Attachment $attachment): StreamedResponse|BinaryFileResponse
    {
        $this->authorize('view', $attachment);

        return Storage::download($attachment->path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment): Response
    {
        $this->authorize('delete', $attachment);

        if ($attachment->path) {
            Storage::delete($attachment->path);
        }

        $attachment->delete();

        return response()->noContent();
    }
}
