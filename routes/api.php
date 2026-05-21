<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LabelController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectMemberController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamMemberController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Explicit model binding for the notification parameter.
Route::model('notification', \App\Models\AppNotification::class);

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/health', HealthController::class);

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth.token')->group(function (): void {
    // Auth / profile
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/password', [AuthController::class, 'changePassword']);

    // Users
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::patch('/users/{user}', [UserController::class, 'update'])->middleware('admin');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('admin');

    // Teams
    Route::get('/teams', [TeamController::class, 'index']);
    Route::post('/teams', [TeamController::class, 'store']);
    Route::get('/teams/{team}', [TeamController::class, 'show']);
    Route::patch('/teams/{team}', [TeamController::class, 'update']);
    Route::delete('/teams/{team}', [TeamController::class, 'destroy']);

    // Team members
    Route::get('/teams/{team}/members', [TeamMemberController::class, 'index']);
    Route::post('/teams/{team}/members', [TeamMemberController::class, 'store']);
    Route::patch('/teams/{team}/members/{user}', [TeamMemberController::class, 'update']);
    Route::delete('/teams/{team}/members/{user}', [TeamMemberController::class, 'destroy']);

    // Projects (nested under team for index/store)
    Route::get('/teams/{team}/projects', [ProjectController::class, 'index']);
    Route::post('/teams/{team}/projects', [ProjectController::class, 'store']);

    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::patch('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{project}/archive', [ProjectController::class, 'archive']);
    Route::post('/projects/{project}/restore', [ProjectController::class, 'restore']);
    Route::get('/projects/{project}/stats', [ProjectController::class, 'stats']);

    // Project members
    Route::get('/projects/{project}/members', [ProjectMemberController::class, 'index']);
    Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store']);
    Route::patch('/projects/{project}/members/{user}', [ProjectMemberController::class, 'update']);
    Route::delete('/projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy']);

    // Project activity
    Route::get('/projects/{project}/activity', [ActivityController::class, 'forProject']);

    // Labels
    Route::get('/projects/{project}/labels', [LabelController::class, 'index']);
    Route::post('/projects/{project}/labels', [LabelController::class, 'store']);
    Route::patch('/labels/{label}', [LabelController::class, 'update']);
    Route::delete('/labels/{label}', [LabelController::class, 'destroy']);

    // Tasks (project-scoped)
    Route::get('/projects/{project}/tasks', [TaskController::class, 'index']);
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store']);

    // Tasks (single)
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::patch('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
    Route::post('/tasks/{task}/assign', [TaskController::class, 'assign']);
    Route::post('/tasks/{task}/unassign', [TaskController::class, 'unassign']);
    Route::post('/tasks/{task}/status', [TaskController::class, 'transition']);
    Route::post('/tasks/{task}/priority', [TaskController::class, 'priority']);
    Route::post('/tasks/{task}/labels', [TaskController::class, 'syncLabels']);
    Route::get('/tasks/{task}/subtasks', [TaskController::class, 'subtasks']);
    Route::post('/tasks/{task}/subtasks', [TaskController::class, 'storeSubtask']);
    Route::post('/tasks/{task}/reorder', [TaskController::class, 'reorder']);
    Route::get('/tasks/{task}/activity', [TaskController::class, 'activity']);

    // Comments
    Route::get('/tasks/{task}/comments', [CommentController::class, 'indexForTask']);
    Route::post('/tasks/{task}/comments', [CommentController::class, 'storeForTask']);
    Route::patch('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    // Attachments
    Route::get('/tasks/{task}/attachments', [AttachmentController::class, 'indexForTask']);
    Route::post('/tasks/{task}/attachments', [AttachmentController::class, 'storeForTask']);
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
        ->name('api.attachments.download');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    // Global activity
    Route::get('/activity', [ActivityController::class, 'index']);

    // Dashboard + search
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/search', [SearchController::class, 'index']);
});
