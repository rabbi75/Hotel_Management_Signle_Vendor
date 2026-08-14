<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Actions\CreatePost;
use App\Modules\Blog\Actions\DuplicatePost;
use App\Modules\Blog\Actions\UpdatePost;
use App\Modules\Blog\DTOs\PostData;
use App\Modules\Blog\Enums\BodyFormat;
use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Http\Controllers\Concerns\ProvidesBlogOptions;
use App\Modules\Blog\Http\Requests\StorePostRequest;
use App\Modules\Blog\Http\Requests\UpdatePostRequest;
use App\Modules\Blog\Http\Resources\PostResource;
use App\Modules\Blog\Models\Post;
use App\Modules\SEO\Models\SeoMeta;
use App\Modules\SEO\Services\SeoScorer;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    use ProvidesBlogOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Post::class);

        $query = Post::query()
            ->with(['author', 'category'])
            ->withCount('comments');

        $table = TableBuilder::for($query, $request, 'posts')
            ->columns([
                Column::make('title', __('Title'))->sortable()->searchable()->locked(),
                Column::make('status', __('Status'))->sortable(),
                Column::make('category', __('Category'))->sortable('category_id'),
                Column::make('author', __('Author')),
                Column::make('published_at', __('Published'))->sortable(),
                Column::make('reading_time', __('Read'))->align('right')->hidden(),
                Column::make('view_count', __('Views'))->sortable()->align('right')->hidden(),
                Column::make('comments_count', __('Comments'))->align('right')->hidden(),
                Column::make('updated_at', __('Updated'))->sortable()->hidden(),
            ])
            ->filters([
                Filter::make('status', __('Status'))->fromEnum(PostStatus::class)->multiple(),
                Filter::make('category_id', __('Category'))->options($this->categoryOptions()),
                Filter::make('author_id', __('Author'))->options($this->authorOptions()),
                Filter::make('published_at', __('Published'))->dateRange(),
                Filter::make('is_featured', __('Featured'))->boolean(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->transform(fn (Post $post): array => (new PostResource($post))->resolve($request));

        return Inertia::render('blog/posts/index', [
            'table' => $table->toArray(),
            'can' => [
                'create' => Gate::allows('create', Post::class),
                'publish' => $request->user()?->can('blog.posts.publish') ?? false,
                'delete' => $request->user()?->can('blog.posts.delete') ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Post::class);

        return Inertia::render('blog/posts/create', [
            'categories' => $this->categoryOptions(),
            'tags' => $this->tagOptions(),
            'formats' => BodyFormat::options(),
            'statuses' => PostStatus::options(),
            'seo' => $this->seoContext(),
        ]);
    }

    public function store(StorePostRequest $request, CreatePost $createPost): RedirectResponse
    {
        $post = $createPost->handle(PostData::fromRequest($request), $request->user()?->getAuthIdentifier());

        return redirect()
            ->route('blog.posts.edit', $post)
            ->with('success', __('Post ":title" created.', ['title' => $post->title]));
    }

    public function edit(Request $request, Post $post, SeoScorer $scorer): Response
    {
        Gate::authorize('update', $post);

        $post->load(['author', 'category', 'tags', 'seo']);

        return Inertia::render('blog/posts/edit', [
            'post' => (new PostResource($post))->resolve($request),
            'categories' => $this->categoryOptions(),
            'tags' => $this->tagOptions(),
            'formats' => BodyFormat::options(),
            'statuses' => PostStatus::options(),
            'seo' => $this->seoContext(),
            'report' => $this->report($scorer, $post),
            'can' => [
                'publish' => Gate::allows('publish', $post),
                'delete' => Gate::allows('delete', $post),
                'duplicate' => Gate::allows('duplicate', $post),
            ],
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post, UpdatePost $updatePost): RedirectResponse
    {
        $updatePost->handle($post, PostData::fromRequest($request));

        return back()->with('success', __('Post saved.'));
    }

    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $post->delete();

        return redirect()
            ->route('blog.posts.index')
            ->with('success', __('Post deleted.'));
    }

    public function duplicate(Request $request, Post $post, DuplicatePost $duplicatePost): RedirectResponse
    {
        Gate::authorize('duplicate', $post);

        $copy = $duplicatePost->handle($post, $request->user()?->getAuthIdentifier());

        return redirect()
            ->route('blog.posts.edit', $copy)
            ->with('success', __('Post duplicated as a draft. Give it its own slug before publishing.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function report(SeoScorer $scorer, Post $post): array
    {
        $relation = $post->relationLoaded('seo') ? $post->getRelation('seo') : null;
        $meta = $relation instanceof SeoMeta ? $relation : null;

        $title = $meta?->title;
        $description = $meta?->description;
        $canonical = $meta?->canonical_url;

        return $scorer->analyse([
            'title' => $title ?? $post->title,
            'description' => $description ?? $post->excerpt,
            'canonical' => $canonical ?? $post->url(),
            'slug' => $post->slug,
            'body_html' => $post->body_html,
            'keyword' => $meta?->keywords,
        ])->toArray();
    }
}
