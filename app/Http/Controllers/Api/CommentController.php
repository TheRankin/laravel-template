<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Http\Requests\Comments\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    public function indexForTask(Task $task, Request $request)
    {
        $this->authorize('viewAny', [Comment::class, $task]);

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $comments = $task->comments()
            ->with('user')
            ->latest()
            ->paginate($perPage);

        return CommentResource::collection($comments);
    }

    public function storeForTask(StoreCommentRequest $request, Task $task): CommentResource
    {
        $this->authorize('create', [Comment::class, $task]);

        $comment = Comment::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'body' => $request->validated()['body'],
        ]);

        $comment->load('user');

        return new CommentResource($comment);
    }

    public function update(UpdateCommentRequest $request, Comment $comment): CommentResource
    {
        $this->authorize('update', $comment);

        $data = $request->validated();
        if (array_key_exists('body', $data)) {
            $comment->body = $data['body'];
            $comment->edited_at = now();
        }
        $comment->save();

        $comment->load('user');

        return new CommentResource($comment);
    }

    public function destroy(Comment $comment): Response
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }
}
