<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Http\Requests\StoreTagRequest;
use App\Modules\Blog\Http\Resources\TagResource;
use App\Modules\Blog\Models\Tag;
use App\Support\DataTable\Column;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Tag::class);

        $query = Tag::query()->withCount('posts');

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
            'table' => $table->toArray(),
            'can' => [
                'manage' => Gate::allows('create', Tag::class),
            ],
        ]);
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        $tag = new Tag;
        $tag->name = (string) $request->string('name');

        $slug = trim((string) $request->input('slug', ''));

        if ($slug !== '') {
            $tag->slug = $slug;
        }

        $tag->save();

        return back()->with('success', __('Tag :name created.', ['name' => $tag->name]));
    }

    public function update(StoreTagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->name = (string) $request->string('name');

        $slug = trim((string) $request->input('slug', ''));

        if ($slug !== '') {
            $tag->slug = $slug;
        }

        $tag->save();

        return back()->with('success', __('Tag updated.'));
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        Gate::authorize('delete', $tag);

        $tag->posts()->detach();
        $tag->delete();

        return back()->with('success', __('Tag deleted.'));
    }
}
