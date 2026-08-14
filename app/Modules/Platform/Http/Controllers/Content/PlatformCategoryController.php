<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Modules\Blog\DTOs\CategoryData;
use App\Modules\Blog\Http\Resources\CategoryResource;
use App\Modules\Blog\Models\Category;
use App\Modules\Platform\Http\Controllers\Content\Concerns\ManagesPlatformContent;
use App\Modules\Platform\Http\Controllers\Content\Concerns\ProvidesPlatformBlogOptions;
use App\Modules\Platform\Http\Requests\Content\StorePlatformCategoryRequest;
use App\Modules\Platform\Http\Requests\Content\UpdatePlatformCategoryRequest;
use App\Support\DataTable\Column;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformCategoryController extends Controller
{
    use ManagesPlatformContent;
    use ProvidesPlatformBlogOptions;

    public function index(Request $request): Response
    {
        $this->authorizeContentOperator($request);

        $query = $this->platformOwned(Category::class)->with('parent')->withCount('posts');

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
            'panel' => 'admin',
            'table' => $table->toArray(),
            'tree' => $this->tree($request),
            'categories' => $this->categoryOptions(),
            'can' => ['manage' => true],
        ]);
    }

    public function store(StorePlatformCategoryRequest $request): RedirectResponse
    {
        $category = new Category;
        $category->fill(CategoryData::fromRequest($request)->toAttributes());
        $category->company_id = null;
        $category->save();

        return back()->with('success', __('Category :name created.', ['name' => $category->name]));
    }

    public function update(UpdatePlatformCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->ensurePlatformOwned($category);

        $category->fill(CategoryData::fromRequest($request)->toUpdateAttributes());
        $category->save();

        return back()->with('success', __('Category updated.'));
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeContentOperator($request);
        $this->ensurePlatformOwned($category);

        $this->platformOwned(Category::class)
            ->where('parent_id', $category->id)
            ->update(['parent_id' => $category->parent_id]);

        $category->delete();

        return back()->with('success', __('Category deleted. Its posts are now uncategorised.'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function tree(Request $request): array
    {
        $roots = $this->platformOwned(Category::class)
            ->whereNull('parent_id')
            ->with('children.children')
            ->withCount('posts')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($roots)->resolve($request);
    }
}
