<?php

declare(strict_types=1);

namespace App\Modules\Blog;

use App\Modules\Blog\Console\PublishScheduledPostsCommand;
use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Comment;
use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Models\Tag;
use App\Modules\Blog\Policies\CategoryPolicy;
use App\Modules\Blog\Policies\CommentPolicy;
use App\Modules\Blog\Policies\PostPolicy;
use App\Modules\Blog\Policies\TagPolicy;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;

class BlogServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Post::class => PostPolicy::class,
        Category::class => CategoryPolicy::class,
        Tag::class => TagPolicy::class,
        Comment::class => CommentPolicy::class,
    ];

    protected function bootModule(): void
    {
        $this->loadViewsFrom($this->path('Resources/views'), 'blog');

        $this->registerMorphMap();
        $this->registerNavigation();

        if ($this->app->runningInConsole()) {
            $this->commands([PublishScheduledPostsCommand::class]);
            $this->scheduleCommands();
        }
    }

    /**
     * Post is stored polymorphically by the SEO module, so its morph key is
     * pinned to a short alias. Without this the fully qualified class name ends
     * up in the database and renaming the namespace becomes a data migration.
     *
     * `morphMap` and not `enforceMorphMap`: enforcing would require every other
     * polymorphic model in the kit — media, activity log, permissions — to be
     * listed here too, and the first one anybody forgot would throw at runtime.
     */
    protected function registerMorphMap(): void
    {
        Relation::morphMap([
            'blog_post' => Post::class,
        ]);
    }

    protected function scheduleCommands(): void
    {
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            // Every five minutes rather than hourly: an author who schedules a
            // post for 09:00 expects it at 09:00, not at some point before ten.
            $schedule->command('blog:publish-scheduled')
                ->everyFiveMinutes()
                ->withoutOverlapping()
                ->onOneServer();
        });
    }

    protected function registerNavigation(): void
    {
        // Content (posts, taxonomy, comments) is managed from the operator
        // console — the tenant shell is reserved for hotel operations.
    }
}
