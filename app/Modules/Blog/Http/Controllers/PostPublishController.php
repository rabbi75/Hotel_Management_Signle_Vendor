<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Actions\PublishPost;
use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Http\Requests\PublishPostRequest;
use App\Modules\Blog\Http\Requests\SchedulePostRequest;
use App\Modules\Blog\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * The publication transitions, separated from PostController because they are
 * gated by a different permission and are the only writes that change what the
 * public can see.
 */
class PostPublishController extends Controller
{
    public function __construct(protected PublishPost $publisher) {}

    public function store(PublishPostRequest $request, Post $post): RedirectResponse
    {
        $at = $request->date('published_at');

        $post = $this->publisher->publish($post, $at === null ? null : CarbonImmutable::instance($at));

        return back()->with('success', $post->status === PostStatus::Scheduled
            ? __('Post scheduled for :date.', ['date' => $post->published_at?->toDayDateTimeString() ?? ''])
            : __('Post published.'));
    }

    public function schedule(SchedulePostRequest $request, Post $post): RedirectResponse
    {
        $at = $request->date('published_at');

        if ($at === null) {
            return back()->with('error', __('A schedule needs a date and time.'));
        }

        $post = $this->publisher->schedule($post, CarbonImmutable::instance($at));

        return back()->with('success', __('Post scheduled for :date.', [
            'date' => $post->published_at?->toDayDateTimeString() ?? '',
        ]));
    }

    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('publish', $post);

        $this->publisher->unpublish($post);

        return back()->with('success', __('Post reverted to a draft. Anyone holding its URL now gets a 404.'));
    }

    public function archive(Post $post): RedirectResponse
    {
        Gate::authorize('publish', $post);

        $this->publisher->archive($post);

        return back()->with('success', __('Post archived.'));
    }
}
