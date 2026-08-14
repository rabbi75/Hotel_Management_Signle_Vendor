<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Enums\CommentStatus;
use App\Modules\Blog\Http\Requests\ModerateCommentsRequest;
use App\Modules\Blog\Http\Resources\CommentResource;
use App\Modules\Blog\Models\Comment;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The moderation queue.
 *
 * Everything here is behind `blog.comments.moderate`; the public submission
 * endpoint lives on {@see BlogController} instead, because it is reachable by
 * strangers and has nothing in common with these actions.
 */
class CommentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Comment::class);

        $query = Comment::query()->with(['post', 'user']);

        $table = TableBuilder::for($query, $request, 'comments')
            ->columns([
                Column::make('author', __('Author'))->searchable('guest_name')->locked(),
                Column::make('body', __('Comment'))->searchable(),
                Column::make('post', __('Post'))->searchable('post.title'),
                Column::make('status', __('Status'))->sortable(),
                Column::make('created_at', __('Received'))->sortable(),
                Column::make('ip_address', __('IP'))->hidden(),
            ])
            ->filters([
                Filter::make('status', __('Status'))->fromEnum(CommentStatus::class)->multiple(),
                Filter::make('created_at', __('Received'))->dateRange(),
            ])
            ->defaultSort('created_at', 'desc')
            ->transform(fn (Comment $comment): array => (new CommentResource($comment))->resolve($request));

        return Inertia::render('blog/comments/index', [
            'table' => $table->toArray(),
            'counts' => $this->counts(),
        ]);
    }

    /**
     * Bulk approve / spam / unapprove / delete.
     */
    public function moderate(ModerateCommentsRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_values(array_map(intval(...), (array) $request->input('ids', [])));
        $action = (string) $request->string('action');

        $comments = Comment::query()->whereIn('id', $ids)->get();

        foreach ($comments as $comment) {
            Gate::authorize($action === 'delete' ? 'delete' : 'moderate', $comment);
        }

        if ($action === 'delete') {
            Comment::query()->whereIn('id', $comments->modelKeys())->delete();

            return back()->with('success', __(':count comments deleted.', ['count' => $comments->count()]));
        }

        $status = match ($action) {
            'approve' => CommentStatus::Approved,
            'spam' => CommentStatus::Spam,
            default => CommentStatus::Pending,
        };

        Comment::query()->whereIn('id', $comments->modelKeys())->update(['status' => $status]);

        return back()->with('success', __(':count comments marked as :status.', [
            'count' => $comments->count(),
            'status' => mb_strtolower($status->label()),
        ]));
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return back()->with('success', __('Comment deleted.'));
    }

    /**
     * @return array<string, int>
     */
    protected function counts(): array
    {
        $counts = [];

        foreach (CommentStatus::cases() as $status) {
            $counts[$status->value] = Comment::query()->where('status', $status)->count();
        }

        return $counts;
    }
}
