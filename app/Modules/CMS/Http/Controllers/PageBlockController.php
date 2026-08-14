<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Http\Requests\ReorderPageBlocksRequest;
use App\Modules\CMS\Http\Requests\StorePageBlockRequest;
use App\Modules\CMS\Http\Requests\UpdatePageBlockRequest;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Models\PageBlock;
use App\Modules\CMS\Services\BlockRegistry;
use App\Modules\CMS\Services\PageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PageBlockController extends Controller
{
    public function __construct(
        protected BlockRegistry $registry,
        protected PageService $pages,
    ) {}

    public function store(StorePageBlockRequest $request, Page $page): RedirectResponse
    {
        $schema = $this->registry->get((string) $request->string('type'));

        abort_if($schema === null, 422);

        /** @var array<string, mixed> $submitted */
        $submitted = (array) $request->input('data', []);

        $this->pages->insertBlock(
            $page,
            $schema,
            $request->has('order') ? $request->integer('order') : null,
            $submitted,
            $request->boolean('is_visible', true),
        );

        return back()->with('success', __('Block added.'));
    }

    public function update(UpdatePageBlockRequest $request, PageBlock $block): RedirectResponse
    {
        $schema = $this->registry->get($block->type);

        abort_if($schema === null, 422);

        /** @var array<string, mixed> $submitted */
        $submitted = (array) $request->input('data', []);

        $block->data = $schema->sanitise($submitted);

        if ($request->has('is_visible')) {
            $block->is_visible = $request->boolean('is_visible');
        }

        $block->save();

        return back();
    }

    /**
     * Persist a drag-and-drop reorder.
     *
     * The whole ordered id list arrives at once and is rewritten in a single
     * transaction, so a partially applied reorder can never leave two blocks
     * sharing a position.
     */
    public function reorder(ReorderPageBlocksRequest $request, Page $page): RedirectResponse
    {
        Gate::authorize('update', $page);

        $this->pages->reorderBlocks($page, $request->ids());

        return back();
    }

    public function toggle(PageBlock $block): RedirectResponse
    {
        Gate::authorize('update', $block->page()->firstOrFail());

        $block->is_visible = ! $block->is_visible;
        $block->save();

        return back();
    }

    public function destroy(PageBlock $block): RedirectResponse
    {
        Gate::authorize('update', $block->page()->firstOrFail());

        $block->delete();

        return back()->with('success', __('Block removed.'));
    }
}
