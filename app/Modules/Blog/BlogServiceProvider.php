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
    /**
     * Public blog URLs stay at /blog; the editor is prefixed in Routes/web.php.
     */
    protected bool $panelPrefixed = false;
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
        $this->app->make(\App\Support\Navigation\NavigationBuilder::class)->register(
            \App\Support\Navigation\NavigationSection::make('Website', 50)->items([
                \App\Support\Navigation\NavigationItem::make('Posts', 'blog.posts.index')
                    ->icon('book')
                    ->permissions('blog.posts.view')
                    ->feature('blog')
                    ->activeWhen('blog.posts.*')
                    ->order(30),

                \App\Support\Navigation\NavigationItem::make('Categories', 'blog.categories.index')
                    ->icon('folder')
                    ->permissions('blog.taxonomy.manage')
                    ->feature('blog')
                    ->activeWhen('blog.categories.*')
                    ->order(40),

                \App\Support\Navigation\NavigationItem::make('Tags', 'blog.tags.index')
                    ->icon('tag')
                    ->permissions('blog.taxonomy.manage')
                    ->feature('blog')
                    ->activeWhen('blog.tags.*')
                    ->order(50),

                \App\Support\Navigation\NavigationItem::make('Comments', 'blog.comments.index')
                    ->icon('message-square')
                    ->permissions('blog.comments.moderate')
                    ->feature('blog')
                    ->activeWhen('blog.comments.*')
                    ->order(60),
            ]),
        );
    }
}
