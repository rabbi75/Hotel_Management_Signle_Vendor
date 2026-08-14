<?php

declare(strict_types=1);

namespace App\Modules\Blog\Actions;

use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Models\Post;
use Carbon\CarbonImmutable;

/**
 * The three publication transitions, kept together so the rule that decides
 * between "published" and "scheduled" lives in one place.
 */
class PublishPost
{
    public function publish(Post $post, ?CarbonImmutable $at = null): Post
    {
        $at ??= now()->toImmutable();

        $post->forceFill([
            // A future date is a schedule, whatever button was pressed; the
            // publish-scheduled command promotes it when the time arrives.
            'status' => $at->isFuture() ? PostStatus::Scheduled : PostStatus::Published,
            'published_at' => $at,
        ])->save();

        return $post;
    }

    public function schedule(Post $post, CarbonImmutable $at): Post
    {
        return $this->publish($post, $at);
    }

    public function unpublish(Post $post): Post
    {
        // published_at is kept: it is a record of when the post *was* live, and
        // clearing it would lose that on every temporary retraction.
        $post->forceFill(['status' => PostStatus::Draft])->save();

        return $post;
    }

    public function archive(Post $post): Post
    {
        $post->forceFill(['status' => PostStatus::Archived])->save();

        return $post;
    }
}
