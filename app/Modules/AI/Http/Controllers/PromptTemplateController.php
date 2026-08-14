<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Http\Requests\StorePromptTemplateRequest;
use App\Modules\AI\Http\Requests\UpdatePromptTemplateRequest;
use App\Modules\AI\Http\Resources\AiPromptTemplateResource;
use App\Modules\AI\Models\AiPromptTemplate;
use App\Modules\AI\Services\ProviderManager;
use App\Modules\User\Models\User;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PromptTemplateController extends Controller
{
    public function __construct(protected ProviderManager $providers) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AiPromptTemplate::class);

        $table = TableBuilder::for(AiPromptTemplate::query()->with('author'), $request, 'templates')
            ->columns([
                Column::make('name', __('Name'))->sortable()->searchable()->locked(),
                Column::make('category', __('Category'))->sortable(),
                Column::make('provider', __('Provider')),
                Column::make('usage_count', __('Uses'))->sortable()->align('right'),
                Column::make('is_shared', __('Shared')),
                Column::make('created_at', __('Created'))->sortable()->hidden(),
            ])
            ->filters([
                Filter::make('is_shared', __('Visibility'))->boolean(),
                Filter::make('provider', __('Provider'))->options($this->providerOptions()),
            ])
            ->defaultSort('name', 'asc')
            ->transform(fn (AiPromptTemplate $template): array => (new AiPromptTemplateResource($template))->resolve($request));

        return Inertia::render('ai/templates/index', [
            'table' => $table->toArray(),
            'providers' => $this->providers->catalogue(),
            'can' => ['manage' => Gate::allows('create', AiPromptTemplate::class)],
        ]);
    }

    public function store(StorePromptTemplateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $template = new AiPromptTemplate([
            'company_id' => current_company_id(),
            'user_id' => $user->id,
            'name' => $request->string('name')->toString(),
            'slug' => $this->uniqueSlug($request->string('name')->toString()),
            'description' => $request->string('description')->toString() ?: null,
            'category' => $request->string('category')->toString() ?: null,
            'prompt' => $request->string('prompt')->toString(),
            'variables' => $this->variables($request),
            'provider' => $request->string('provider')->toString() ?: null,
            'model' => $request->string('model')->toString() ?: null,
            'is_shared' => $request->boolean('is_shared'),
        ]);

        $template->save();

        return back()->with('success', __('Template :name created.', ['name' => $template->name]));
    }

    public function update(UpdatePromptTemplateRequest $request, AiPromptTemplate $template): RedirectResponse
    {
        // Absent key means "leave unchanged"; an explicit null clears the field.
        $attributes = [];

        foreach (['name', 'description', 'category', 'prompt', 'provider', 'model'] as $field) {
            if ($request->has($field)) {
                $value = $request->string($field)->toString();
                $attributes[$field] = in_array($field, ['name', 'prompt'], true) ? $value : ($value ?: null);
            }
        }

        if ($request->has('variables')) {
            $attributes['variables'] = $this->variables($request);
        }

        if ($request->has('is_shared')) {
            $attributes['is_shared'] = $request->boolean('is_shared');
        }

        $template->fill($attributes)->save();

        return back()->with('success', __('Template updated.'));
    }

    public function destroy(AiPromptTemplate $template): RedirectResponse
    {
        Gate::authorize('delete', $template);

        $template->delete();

        return back()->with('success', __('Template deleted.'));
    }

    /**
     * @return list<array{name: string, label: string, type: string, required: bool}>
     */
    protected function variables(Request $request): array
    {
        /** @var list<array<string, mixed>> $raw */
        $raw = (array) $request->input('variables', []);

        return array_map(static fn (array $variable): array => [
            'name' => (string) ($variable['name'] ?? ''),
            'label' => (string) ($variable['label'] ?? ''),
            'type' => (string) ($variable['type'] ?? 'text'),
            'required' => (bool) ($variable['required'] ?? false),
        ], $raw);
    }

    /**
     * @return array<string, string>
     */
    protected function providerOptions(): array
    {
        $options = [];

        foreach ($this->providers->catalogue() as $provider) {
            $options[$provider['label']] = $provider['key'];
        }

        return $options;
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 1;

        while (AiPromptTemplate::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }
}
