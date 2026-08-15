<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Http\Resources\TagResource;
use App\Modules\Blog\Models\Tag;
use App\Modules\Platform\Http\Controllers\Content\Concerns\ManagesPlatformContent;
use App\Modules\Platform\Http\Requests\Content\StorePlatformTagRequest;
use App\Support\DataTable\Column;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformTagController extends Controller
{
    use ManagesPlatformContent;

    public function index(Request $request): Response
    {
        $this->authorizeContentOperator($request);

        $query = $this->platformOwned(Tag::class)->withCount('posts');

        $table = TableBuilder::for($query, $request, 'tags')
            ->columns([
                Column::make('name', __('Name'))->sortable()->searchable()->locked(),
                Column::make('slug', __('Slug'))->sortable()->searchable(),
                Column::make('posts_count', __('Posts'))->align('right'),
                Column::make('created_at', __('Created'))->sortable()->hidden(),
            ])
            ->defaultSort('name', 'desc')
            ->transform(fn (Tag $tag): array => (new TagResource($tag))->resolve($request));

        return Inertia::render('blog/tags/index', [
            'panel' => 'admin',
            'table' => $table->toArray(),
            'can' => ['manage' => true],
        ]);
    }

    public function store(StorePlatformTagRequest $request): RedirectResponse
    {
        $tag = new Tag;
        $tag->name = (string) $request->string('name');

        $slug = trim((string) $request->input('slug', ''));

        if ($slug !== '') {
            $tag->slug = $slug;
        }

        $tag->company_id = null;
        $tag->save();

        return back()->with('success', __('Tag :name created.', ['name' => $tag->name]));
    }

    public function update(StorePlatformTagRequest $request, Tag $tag): RedirectResponse
    {
        $this->authorizeContentOperator($request);
        $this->ensurePlatformOwned($tag);

        $tag->name = (string) $request->string('name');

        $slug = trim((string) $request->input('slug', ''));

        if ($slug !== '') {
            $tag->slug = $slug;
        }

        $tag->save();

        return back()->with('success', __('Tag updated.'));
    }

    public function destroy(Request $request, Tag $tag): RedirectResponse
    {
        $this->authorizeContentOperator($request);
        $this->ensurePlatformOwned($tag);

        $tag->posts()->detach();
        $tag->delete();

        return back()->with('success', __('Tag deleted.'));
    }
}
