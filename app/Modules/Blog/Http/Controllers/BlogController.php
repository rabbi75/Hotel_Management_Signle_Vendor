<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Actions\CreateComment;
use App\Modules\Blog\DTOs\CommentData;
use App\Modules\Blog\Enums\CommentStatus;
use App\Modules\Blog\Http\Requests\StoreCommentRequest;
use App\Modules\Blog\Http\Resources\CommentResource;
use App\Modules\Blog\Http\Resources\PostResource;
use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Models\Tag;
use App\Modules\SEO\Services\SeoManager;
use App\Modules\SEO\Services\StructuredData;
use App\Modules\SEO\Support\PublicWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The reader-facing blog.
 *
 * Every action resolves the workspace itself rather than relying on the session
 * middleware, because these routes are reached by people who have never logged
 * in: without an explicit tenant the global scope is inert and one URL would
 * serve every customer's posts. Route-model binding is avoided here for the
 * same reason — the lookup must happen after the tenant is pinned.
 */
class BlogController extends Controller
{
    public function __construct(
        protected PublicWorkspace $workspace,
        protected SeoManager $seo,
    ) {}

    public function index(Request $request): Response
    {
        $this->workspace->resolve($request);

        $this->seo->share($this->seo->forPage(
            title: __('Blog'),
            description: __('Articles and updates.'),
            url: route('blog.public.index'),
            graphs: [StructuredData::website((string) config('saas.brand.name'), (string) config('app.url'))],
        ));

        return Inertia::render('blog/public/index', [
            'posts' => $this->page($request, Post::query()->published()),
            'categories' => $this->categories($request),
            'tags' => $this->tags($request),
            'archive' => null,
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $this->workspace->resolve($request);

        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->with(['author', 'category', 'tags', 'seo'])
            ->first();

        // A draft, a scheduled post and a post belonging to another workspace
        // are all indistinguishable from "no such post" out here, deliberately:
        // a 403 would confirm that the slug exists.
        if ($post === null) {
            throw new NotFoundHttpException;
        }

        $this->recordView($post);
        $this->shareArticleMeta($post);

        return Inertia::render('blog/public/show', [
            'post' => (new PostResource($post))->resolve($request),
            'comments' => $this->comments($request, $post),
            'comments_enabled' => (bool) config('saas.blog.comments_enabled', false) && $post->allow_comments,
            'related' => $this->related($request, $post),
        ]);
    }

    public function category(Request $request, string $slug): Response
    {
        $this->workspace->resolve($request);

        $category = Category::query()->where('slug', $slug)->first();

        if ($category === null) {
            throw new NotFoundHttpException;
        }

        $this->seo->share($this->seo->forPage(
            title: $category->name,
            description: $category->description,
            url: route('blog.public.category', $category->slug),
        ));

        return Inertia::render('blog/public/index', [
            'posts' => $this->page($request, Post::query()->published()->whereIn('category_id', $category->descendantIds())),
            'categories' => $this->categories($request),
            'tags' => $this->tags($request),
            'archive' => ['type' => 'category', 'name' => $category->name, 'description' => $category->description],
        ]);
    }

    public function tag(Request $request, string $slug): Response
    {
        $this->workspace->resolve($request);

        $tag = Tag::query()->where('slug', $slug)->first();

        if ($tag === null) {
            throw new NotFoundHttpException;
        }

        $this->seo->share($this->seo->forPage(
            title: __('Posts tagged :tag', ['tag' => $tag->name]),
            url: route('blog.public.tag', $tag->slug),
        ));

        $query = Post::query()
            ->published()
            ->whereHas('tags', fn (Builder $tags) => $tags->whereKey($tag->id));

        return Inertia::render('blog/public/index', [
            'posts' => $this->page($request, $query),
            'categories' => $this->categories($request),
            'tags' => $this->tags($request),
            'archive' => ['type' => 'tag', 'name' => $tag->name, 'description' => null],
        ]);
    }

    /**
     * RSS 2.0. Rendered from a Blade view rather than assembled here so the
     * escaping rules stay the template engine's problem.
     */
    public function feed(Request $request): HttpResponse
    {
        $this->workspace->resolve($request);

        $posts = Post::query()
            ->published()
            ->with('author')
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        return response()
            ->view('blog::feed', ['posts' => $posts, 'title' => (string) config('saas.brand.name')])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public function comment(StoreCommentRequest $request, string $slug, CreateComment $createComment): RedirectResponse
    {
        $this->workspace->resolve($request);

        $post = Post::query()->published()->where('slug', $slug)->first();

        if ($post === null) {
            throw new NotFoundHttpException;
        }

        $comment = $createComment->handle($post, CommentData::fromRequest($request));

        return back()->with('success', $comment->status === CommentStatus::Approved
            ? __('Thanks — your comment is live.')
            : __('Thanks — your comment is awaiting moderation.'));
    }

    /**
     * @param  Builder<Post>  $query
     * @return array<string, mixed>
     */
    protected function page(Request $request, Builder $query): array
    {
        /** @var LengthAwarePaginator<int, Post> $paginator */
        $paginator = $query
            ->with(['author', 'category'])
            ->orderByDesc('published_at')
            ->paginate((int) config('saas.blog.per_page', 12))
            ->withQueryString();

        return [
            'data' => $paginator->getCollection()
                ->map(fn (Post $post): array => (new PostResource($post))->resolve($request))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function comments(Request $request, Post $post): array
    {
        if (! $post->allow_comments) {
            return [];
        }

        $comments = $post->approvedComments()
            ->with(['user', 'replies.user'])
            ->oldest()
            ->get();

        return CommentResource::collection($comments)->resolve($request);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function related(Request $request, Post $post): array
    {
        if ($post->category_id === null) {
            return [];
        }

        $related = Post::query()
            ->published()
            ->where('category_id', $post->category_id)
            ->whereKeyNot($post->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return $related->map(fn (Post $item): array => (new PostResource($item))->resolve($request))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function categories(Request $request): array
    {
        return Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(static fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function tags(Request $request): array
    {
        return Tag::query()
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'slug'])
            ->map(static fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])
            ->values()
            ->all();
    }

    protected function shareArticleMeta(Post $post): void
    {
        $article = StructuredData::article(
            headline: $post->title,
            description: $post->excerpt,
            url: $post->url(),
            image: $post->featured_image,
            authorName: $post->author?->name,
            publisherName: (string) config('saas.brand.name'),
            publishedAt: $post->published_at,
            modifiedAt: $post->updated_at,
        );

        $breadcrumbs = StructuredData::breadcrumbList([
            ['name' => __('Blog'), 'url' => route('blog.public.index')],
            ['name' => $post->title, 'url' => $post->url()],
        ]);

        $this->seo->share($this->seo->resolve($post, [$article, $breadcrumbs]));
    }

    /**
     * A view counter that never blocks the response and never triggers a model
     * event — this is a metric, not a change to the post.
     */
    protected function recordView(Post $post): void
    {
        Post::query()->whereKey($post->id)->update(['view_count' => $post->view_count + 1]);
    }
}
