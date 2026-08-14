<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Blog\DTOs\CategoryData;
use App\Modules\Blog\Http\Controllers\Concerns\ProvidesBlogOptions;
use App\Modules\Blog\Http\Requests\StoreCategoryRequest;
use App\Modules\Blog\Http\Requests\UpdateCategoryRequest;
use App\Modules\Blog\Http\Resources\CategoryResource;
use App\Modules\Blog\Models\Category;
use App\Support\DataTable\Column;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    use ProvidesBlogOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Category::class);

        $query = Category::query()->with('parent')->withCount('posts');

        $table = TableBuilder::for($query, $request, 'categories')
            ->columns([
                Column::make('name', __('Name'))->sortable()->searchable()->locked(),
                Column::make('slug', __('Slug'))->sortable()->searchable(),
                Column::make('parent', __('Parent'))->sortable('parent_id'),
                Column::make('posts_count', __('Posts'))->align('right'),
                Column::make('created_at', __('Created'))->sortable()->hidden(),
            ])
            ->defaultSort('name', 'asc')
            ->transform(fn (Category $category): array => (new CategoryResource($category))->resolve($request));

        return Inertia::render('blog/categories/index', [
            'table' => $table->toArray(),
            'tree' => $this->tree($request),
            'categories' => $this->categoryOptions(),
            'can' => [
                'manage' => Gate::allows('create', Category::class),
            ],
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = new Category;
        $category->fill(CategoryData::fromRequest($request)->toAttributes());
        $category->save();

        return back()->with('success', __('Category :name created.', ['name' => $category->name]));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->fill(CategoryData::fromRequest($request)->toUpdateAttributes());
        $category->save();

        return back()->with('success', __('Category updated.'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        // Children are promoted rather than cascaded: deleting a grouping
        // should never silently delete the things it grouped.
        Category::query()->where('parent_id', $category->id)->update(['parent_id' => $category->parent_id]);

        $category->delete();

        return back()->with('success', __('Category deleted. Its posts are now uncategorised.'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function tree(Request $request): array
    {
        $roots = Category::query()
            ->whereNull('parent_id')
            ->with('children.children')
            ->withCount('posts')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($roots)->resolve($request);
    }
}
