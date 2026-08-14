<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Models\PageBlock;
use App\Modules\CMS\Services\BlockRegistry;
use App\Modules\CMS\Services\PageService;
use App\Modules\Platform\Http\Controllers\CMS\Concerns\ManagesPlatformPages;
use App\Modules\Platform\Http\Requests\CMS\ReorderPlatformPageBlocksRequest;
use App\Modules\Platform\Http\Requests\CMS\StorePlatformPageBlockRequest;
use App\Modules\Platform\Http\Requests\CMS\UpdatePlatformPageBlockRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Block content on a platform-owned page.
 *
 * The block schemas do the sanitising, exactly as they do for a tenant; all
 * this adds is the ownership check.
 */
class PlatformPageBlockController extends Controller
{
    use ManagesPlatformPages;

    public function __construct(
        protected BlockRegistry $registry,
        protected PageService $pages,
    ) {}

    public function store(StorePlatformPageBlockRequest $request, Page $page): RedirectResponse
    {
        $this->ensurePlatformPage($page);

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

    public function update(UpdatePlatformPageBlockRequest $request, PageBlock $block): RedirectResponse
    {
        $this->ensurePlatformBlock($block);

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
     * Persist a drag-and-drop reorder in one transaction, so a partial write can
     * never leave two blocks sharing a position.
     */
    public function reorder(ReorderPlatformPageBlocksRequest $request, Page $page): RedirectResponse
    {
        $this->ensurePlatformPage($page);

        $this->pages->reorderBlocks($page, $request->ids());

        return back();
    }

    public function toggle(Request $request, PageBlock $block): RedirectResponse
    {
        $this->authorizeOperator($request);
        $this->ensurePlatformBlock($block);

        $block->is_visible = ! $block->is_visible;
        $block->save();

        return back();
    }

    public function destroy(Request $request, PageBlock $block): RedirectResponse
    {
        $this->authorizeOperator($request);
        $this->ensurePlatformBlock($block);

        $block->delete();

        return back()->with('success', __('Block removed.'));
    }
}
