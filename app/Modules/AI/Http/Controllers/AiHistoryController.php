<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Enums\GenerationStatus;
use App\Modules\AI\Http\Resources\AiGenerationResource;
use App\Modules\AI\Models\AiGeneration;
use App\Modules\AI\Services\ProviderManager;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AiHistoryController extends Controller
{
    public function __construct(protected ProviderManager $providers) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AiGeneration::class);

        $table = TableBuilder::for(AiGeneration::query()->with(['user', 'template']), $request, 'generations')
            ->columns([
                Column::make('created_at', __('When'))->sortable()->locked(),
                Column::make('provider', __('Provider'))->sortable(),
                Column::make('model', __('Model'))->searchable(),
                Column::make('input', __('Prompt'))->searchable(),
                Column::make('status', __('Status'))->sortable(),
                Column::make('total_tokens', __('Tokens'))->align('right'),
                Column::make('credits_charged', __('Credits'))->sortable()->align('right'),
                Column::make('user', __('User'))->hidden(),
            ])
            ->filters([
                Filter::make('provider', __('Provider'))->options($this->providerOptions()),
                Filter::make('status', __('Status'))->fromEnum(GenerationStatus::class),
                Filter::make('created_at', __('Date'))->dateRange(),
            ])
            ->defaultSort('created_at')
            ->transform(fn (AiGeneration $generation): array => (new AiGenerationResource($generation))->resolve($request));

        return Inertia::render('ai/history/index', [
            'table' => $table->toArray(),
        ]);
    }

    public function show(Request $request, AiGeneration $generation): JsonResponse
    {
        Gate::authorize('view', $generation);

        return new JsonResponse([
            'generation' => (new AiGenerationResource($generation->load(['user', 'template'])))->resolve($request),
        ]);
    }

    public function destroy(AiGeneration $generation): RedirectResponse
    {
        Gate::authorize('delete', $generation);

        $generation->delete();

        return back()->with('success', __('Generation deleted.'));
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
}
