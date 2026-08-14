<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Actions\CreatePost;
use App\Modules\Blog\Actions\DuplicatePost;
use App\Modules\Blog\Actions\UpdatePost;
use App\Modules\Blog\DTOs\PostData;
use App\Modules\Blog\Enums\BodyFormat;
use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Http\Resources\PostResource;
use App\Modules\Blog\Models\Post;
use App\Modules\Platform\Http\Controllers\Content\Concerns\ManagesPlatformContent;
use App\Modules\Platform\Http\Controllers\Content\Concerns\ProvidesPlatformBlogOptions;
use App\Modules\Platform\Http\Requests\Content\StorePlatformPostRequest;
use App\Modules\Platform\Http\Requests\Content\UpdatePlatformPostRequest;
use App\Modules\SEO\Models\SeoMeta;
use App\Modules\SEO\Services\SeoScorer;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformPostController extends Controller
{
    use ManagesPlatformContent;
    use ProvidesPlatformBlogOptions;

    public function index(Request $request): Response
    {
        $this->authorizeContentOperator($request);

        $query = $this->platformOwned(Post::class)
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
            'panel' => 'admin',
            'table' => $table->toArray(),
            'can' => ['create' => true, 'publish' => true, 'delete' => true],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeContentOperator($request);

        return Inertia::render('blog/posts/create', [
            'panel' => 'admin',
            'categories' => $this->categoryOptions(),
            'tags' => $this->tagOptions(),
            'formats' => BodyFormat::options(),
            'statuses' => PostStatus::options(),
            'seo' => $this->seoContext(),
        ]);
    }

    public function store(StorePlatformPostRequest $request, CreatePost $createPost): RedirectResponse
    {
        $post = $createPost->handle(PostData::fromRequest($request), null);

        if ($post->getAttribute('company_id') !== null) {
            $post->company_id = null;
            $post->save();
        }

        return redirect()
            ->route('admin.blog.posts.edit', $post)
            ->with('success', __('Post ":title" created.', ['title' => $post->title]));
    }

    public function edit(Request $request, Post $post, SeoScorer $scorer): Response
    {
        $this->authorizeContentOperator($request);
        $this->ensurePlatformOwned($post);

        $post->load(['author', 'category', 'tags', 'seo']);

        return Inertia::render('blog/posts/edit', [
            'panel' => 'admin',
            'post' => (new PostResource($post))->resolve($request),
            'categories' => $this->categoryOptions(),
            'tags' => $this->tagOptions(),
            'formats' => BodyFormat::options(),
            'statuses' => PostStatus::options(),
            'seo' => $this->seoContext(),
            'report' => $this->report($scorer, $post),
            'can' => ['publish' => true, 'delete' => true, 'duplicate' => true],
        ]);
    }

    public function update(UpdatePlatformPostRequest $request, Post $post, UpdatePost $updatePost): RedirectResponse
    {
        $this->ensurePlatformOwned($post);

        $updatePost->handle($post, PostData::fromRequest($request));

        return back()->with('success', __('Post saved.'));
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeContentOperator($request);
        $this->ensurePlatformOwned($post);

        $post->delete();

        return redirect()
            ->route('admin.blog.posts.index')
            ->with('success', __('Post deleted.'));
    }

    public function duplicate(Request $request, Post $post, DuplicatePost $duplicatePost): RedirectResponse
    {
        $this->authorizeContentOperator($request);
        $this->ensurePlatformOwned($post);

        $copy = $duplicatePost->handle($post, null);

        if ($copy->getAttribute('company_id') !== null) {
            $copy->company_id = null;
            $copy->save();
        }

        return redirect()
            ->route('admin.blog.posts.edit', $copy)
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
