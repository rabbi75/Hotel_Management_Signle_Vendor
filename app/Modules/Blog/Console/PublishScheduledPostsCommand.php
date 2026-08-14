<?php

declare(strict_types=1);

namespace App\Modules\Blog\Console;

use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Models\Post;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Console\Command;

/**
 * Promotes scheduled posts whose time has arrived.
 *
 * Runs without a tenant, so the query is explicitly unscoped: this is a
 * cross-workspace maintenance job and every workspace's schedule must be
 * honoured by the one cron entry.
 */
class PublishScheduledPostsCommand extends Command
{
    protected $signature = 'blog:publish-scheduled';

    protected $description = 'Publish scheduled blog posts whose publication time has passed';

    public function handle(CurrentCompany $tenant): int
    {
        $published = $tenant->bypass(function (): int {
            $due = Post::query()->due()->get();

            foreach ($due as $post) {
                // forceFill + save rather than a mass update so Scout's model
                // observer fires and the post enters the search index at the
                // same moment it becomes visible.
                $post->forceFill(['status' => PostStatus::Published])->save();

                $this->line("Published: {$post->title}");
            }

            return $due->count();
        });

        $this->info($published === 0
            ? 'No scheduled posts were due.'
            : "Published {$published} scheduled post(s).");

        return self::SUCCESS;
    }
}
