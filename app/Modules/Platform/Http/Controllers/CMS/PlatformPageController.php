<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Modules\CMS\DTOs\PageData;
use App\Modules\CMS\Enums\PageStatus;
use App\Modules\CMS\Http\Resources\PageResource;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Services\BlockRegistry;
use App\Modules\CMS\Services\PageService;
use App\Modules\Platform\Http\Controllers\CMS\Concerns\ManagesPlatformPages;
use App\Modules\Platform\Http\Requests\CMS\StorePlatformPageRequest;
use App\Modules\Platform\Http\Requests\CMS\UpdatePlatformPageRequest;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public site's pages, owned by the platform rather than by a tenant.
 *
 * Renders the same Inertia components as the tenant CMS with `panel: 'admin'`,
 * which is what tells the editor to resolve `admin.cms.*` route names and wrap
 * itself in the console shell.
 */
class PlatformPageController extends Controller
{
    use ManagesPlatformPages;

    public function __construct(
        protected PageService $pages,
        protected BlockRegistry $blocks,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeOperator($request);

        $query = $this->platformPages()->with(['parent'])->withCount('blocks');

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
            'panel' => 'admin',
            'table' => $table->toArray(),
            'can' => ['create' => true, 'publish' => true, 'delete' => true],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeOperator($request);

        return Inertia::render('cms/pages/create', [
            'panel' => 'admin',
            'parents' => $this->parentOptions(),
            'blockTypes' => $this->blocks->toArray(),
            'reservedSlugs' => $this->reservedSlugs(),
        ]);
    }

    public function store(StorePlatformPageRequest $request): RedirectResponse
    {
        $page = $this->pages->create(PageData::fromRequest($request), null, null);

        $page->created_by_admin_id = $request->user('admin')?->id;
        $page->save();

        return redirect()
            ->route('admin.cms.pages.edit', $page)
            ->with('success', __('Page :title created.', ['title' => $page->title]));
    }

    public function edit(Request $request, Page $page): Response
    {
        $this->authorizeOperator($request);
        $this->ensurePlatformPage($page);

        return Inertia::render('cms/pages/edit', [
            'panel' => 'admin',
            'page' => (new PageResource($page->load(['parent', 'blocks'])))->resolve($request),
            'parents' => $this->parentOptions($page->id),
            'blockTypes' => $this->blocks->toArray(),
            'reservedSlugs' => $this->reservedSlugs(),
            'can' => ['publish' => true, 'delete' => true],
        ]);
    }

    public function update(UpdatePlatformPageRequest $request, Page $page): RedirectResponse
    {
        $this->ensurePlatformPage($page);

        $this->pages->update($page, PageData::fromRequest($request));

        return back()->with('success', __('Page updated.'));
    }

    public function duplicate(Request $request, Page $page): RedirectResponse
    {
        $this->authorizeOperator($request);
        $this->ensurePlatformPage($page);

        $copy = $this->pages->duplicate($page);
        $copy->created_by_admin_id = $request->user('admin')?->id;
        $copy->save();

        return redirect()
            ->route('admin.cms.pages.edit', $copy)
            ->with('success', __('Page duplicated.'));
    }

    public function destroy(Request $request, Page $page): RedirectResponse
    {
        $this->authorizeOperator($request);
        $this->ensurePlatformPage($page);

        $page->delete();

        return redirect()
            ->route('admin.cms.pages.index')
            ->with('success', __('Page deleted.'));
    }

    /**
     * Platform pages this one could be nested under; its own row is excluded so
     * the picker cannot be used to create a cycle.
     *
     * @return array<array-key, string>
     */
    protected function parentOptions(?int $excludeId = null): array
    {
        $pages = $this->platformPages()
            ->when($excludeId !== null, static fn (Builder $query): Builder => $query->whereKeyNot($excludeId))
            ->orderBy('title')
            ->get(['id', 'title']);

        $options = [];

        foreach ($pages as $page) {
            $options[(string) $page->id] = $page->title;
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    protected function reservedSlugs(): array
    {
        return array_values(array_map(strval(...), (array) config('saas.cms.reserved_slugs', [])));
    }
}
