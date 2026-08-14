<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CMS\DTOs\PageData;
use App\Modules\CMS\Enums\PageStatus;
use App\Modules\CMS\Http\Requests\StorePageRequest;
use App\Modules\CMS\Http\Requests\UpdatePageRequest;
use App\Modules\CMS\Http\Resources\PageResource;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Services\BlockRegistry;
use App\Modules\CMS\Services\PageService;
use App\Modules\User\Models\User;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function __construct(
        protected PageService $pages,
        protected BlockRegistry $blocks,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Page::class);

        $query = Page::query()->with(['parent', 'author'])->withCount('blocks');

        $table = TableBuilder::for($query, $request, 'pages')
            ->columns([
                Column::make('title', __('Title'))->sortable()->searchable()->locked(),
                Column::make('slug', __('Slug'))->sortable()->searchable(),
                Column::make('status', __('Status'))->sortable(),
                Column::make('parent', __('Parent'))->sortable('parent_id')->hidden(),
                Column::make('blocks_count', __('Blocks'))->align('right'),
                Column::make('published_at', __('Published'))->sortable(),
                Column::make('updated_at', __('Updated'))->sortable()->hidden(),
            ])
            ->filters([
                Filter::make('status', __('Status'))->fromEnum(PageStatus::class),
                Filter::make('updated', __('Updated'))->dateRange()->column('updated_at'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->transform(fn (Page $page): array => (new PageResource($page))->resolve($request));

        return Inertia::render('cms/pages/index', [
            'table' => $table->toArray(),
            'can' => [
                'create' => Gate::allows('create', Page::class),
                'publish' => $this->user($request)->can('cms.pages.publish'),
                'delete' => $this->user($request)->can('cms.pages.delete'),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Page::class);

        return Inertia::render('cms/pages/create', [
            'parents' => $this->parentOptions(),
            'blockTypes' => $this->blocks->toArray(),
            'reservedSlugs' => array_values(array_map(strval(...), (array) config('saas.cms.reserved_slugs', []))),
        ]);
    }

    public function store(StorePageRequest $request): RedirectResponse
    {
        $page = $this->pages->create(PageData::fromRequest($request), $this->user($request));

        return redirect()
            ->route('cms.pages.edit', $page)
            ->with('success', __('Page :title created.', ['title' => $page->title]));
    }

    public function edit(Request $request, Page $page): Response
    {
        Gate::authorize('update', $page);

        return Inertia::render('cms/pages/edit', [
            'page' => (new PageResource($page->load(['parent', 'author', 'blocks'])))->resolve($request),
            'parents' => $this->parentOptions($page->id),
            'blockTypes' => $this->blocks->toArray(),
            'reservedSlugs' => array_values(array_map(strval(...), (array) config('saas.cms.reserved_slugs', []))),
            'can' => [
                'publish' => Gate::allows('publish', $page),
                'delete' => Gate::allows('delete', $page),
            ],
        ]);
    }

    public function update(UpdatePageRequest $request, Page $page): RedirectResponse
    {
        $this->pages->update($page, PageData::fromRequest($request));

        return back()->with('success', __('Page updated.'));
    }

    public function duplicate(Request $request, Page $page): RedirectResponse
    {
        Gate::authorize('duplicate', $page);

        $copy = $this->pages->duplicate($page, $this->user($request));

        return redirect()
            ->route('cms.pages.edit', $copy)
            ->with('success', __('Page duplicated.'));
    }

    public function destroy(Page $page): RedirectResponse
    {
        Gate::authorize('delete', $page);

        $page->delete();

        return redirect()
            ->route('cms.pages.index')
            ->with('success', __('Page deleted.'));
    }

    /**
     * Pages this one could be nested under. Its own subtree is excluded so the
     * picker cannot be used to create a cycle.
     *
     * @return array<array-key, string>
     */
    protected function parentOptions(?int $excludeId = null): array
    {
        $pages = Page::query()
            ->when($excludeId !== null, static fn (Builder $query): Builder => $query->whereKeyNot($excludeId))
            ->orderBy('title')
            ->get(['id', 'title']);

        $options = [];

        foreach ($pages as $page) {
            $options[(string) $page->id] = $page->title;
        }

        return $options;
    }

    protected function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
