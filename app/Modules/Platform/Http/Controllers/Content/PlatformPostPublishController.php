<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Actions\PublishPost;
use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Models\Post;
use App\Modules\Platform\Http\Controllers\Content\Concerns\ManagesPlatformContent;
use App\Modules\Platform\Http\Requests\Content\PublishPlatformPostRequest;
use App\Modules\Platform\Http\Requests\Content\SchedulePlatformPostRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformPostPublishController extends Controller
{
    use ManagesPlatformContent;

    public function __construct(protected PublishPost $publisher) {}

    public function store(PublishPlatformPostRequest $request, Post $post): RedirectResponse
    {
        $this->ensurePlatformOwned($post);

        $at = $request->date('published_at');

        $post = $this->publisher->publish($post, $at === null ? null : CarbonImmutable::instance($at));

        return back()->with('success', $post->status === PostStatus::Scheduled
            ? __('Post scheduled for :date.', ['date' => $post->published_at?->toDayDateTimeString() ?? ''])
            : __('Post published.'));
    }

    public function schedule(SchedulePlatformPostRequest $request, Post $post): RedirectResponse
    {
        $this->ensurePlatformOwned($post);

        $at = $request->date('published_at');

        if ($at === null) {
            return back()->with('error', __('A schedule needs a date and time.'));
        }

        $post = $this->publisher->schedule($post, CarbonImmutable::instance($at));

        return back()->with('success', __('Post scheduled for :date.', [
            'date' => $post->published_at?->toDayDateTimeString() ?? '',
        ]));
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeContentOperator($request);
        $this->ensurePlatformOwned($post);

        $this->publisher->unpublish($post);

        return back()->with('success', __('Post reverted to a draft. Anyone holding its URL now gets a 404.'));
    }

    public function archive(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeContentOperator($request);
        $this->ensurePlatformOwned($post);

        $this->publisher->archive($post);

        return back()->with('success', __('Post archived.'));
    }
}
