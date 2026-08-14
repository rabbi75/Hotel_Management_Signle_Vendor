<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Enums\CommentStatus;
use App\Modules\Blog\Http\Resources\CommentResource;
use App\Modules\Blog\Models\Comment;
use App\Modules\Platform\Http\Controllers\Content\Concerns\ManagesPlatformContent;
use App\Modules\Platform\Http\Requests\Content\ModeratePlatformCommentsRequest;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformCommentController extends Controller
{
    use ManagesPlatformContent;

    public function index(Request $request): Response
    {
        $this->authorizeContentOperator($request);

        $query = $this->platformOwned(Comment::class)->with(['post', 'user']);

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
            'panel' => 'admin',
            'table' => $table->toArray(),
            'counts' => $this->counts(),
        ]);
    }

    public function moderate(ModeratePlatformCommentsRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_values(array_map(intval(...), (array) $request->input('ids', [])));
        $action = (string) $request->string('action');

        $comments = $this->platformOwned(Comment::class)->whereIn('id', $ids)->get();

        if ($action === 'delete') {
            Comment::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $comments->modelKeys())
                ->whereNull('company_id')
                ->delete();

            return back()->with('success', __(':count comments deleted.', ['count' => $comments->count()]));
        }

        $status = match ($action) {
            'approve' => CommentStatus::Approved,
            'spam' => CommentStatus::Spam,
            default => CommentStatus::Pending,
        };

        Comment::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $comments->modelKeys())
            ->whereNull('company_id')
            ->update(['status' => $status]);

        return back()->with('success', __(':count comments marked as :status.', [
            'count' => $comments->count(),
            'status' => mb_strtolower($status->label()),
        ]));
    }

    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        $this->authorizeContentOperator($request);
        $this->ensurePlatformOwned($comment);

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
            $counts[$status->value] = $this->platformOwned(Comment::class)
                ->where('status', $status)
                ->count();
        }

        return $counts;
    }
}
