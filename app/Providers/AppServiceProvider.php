<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Policies\AttachmentPolicy;
use App\Policies\CommentPolicy;
use App\Policies\LabelPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TeamPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(Label::class, LabelPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
    }
}
