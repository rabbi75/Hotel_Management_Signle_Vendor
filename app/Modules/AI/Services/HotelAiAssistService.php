<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

use App\Modules\AI\Actions\EnsureHotelPromptPack;
use App\Modules\AI\DTOs\AiRequest;
use App\Modules\AI\Models\AiGeneration;
use App\Modules\AI\Models\AiPromptTemplate;
use App\Modules\AI\Support\HotelPromptPack;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Generator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Runs a hotel-context assist: ensure prompt pack, pack context, render template, generate.
 */
class HotelAiAssistService
{
    public function __construct(
        protected EnsureHotelPromptPack $ensurePack,
        protected HotelAiContextBuilder $contexts,
        protected TemplateRenderer $renderer,
        protected GenerationService $generations,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: AiRequest, 1: AiPromptTemplate}
     */
    public function prepare(string $action, array $payload, Company $company): array
    {
        $this->ensurePack->handle($company);

        $slug = HotelPromptPack::slugForAction($action);

        if ($slug === null) {
            throw ValidationException::withMessages([
                'action' => __('Unknown AI assist action.'),
            ]);
        }

        $template = AiPromptTemplate::query()
            ->where('slug', $slug)
            ->where('company_id', $company->id)
            ->first();

        if (! $template instanceof AiPromptTemplate) {
            throw ValidationException::withMessages([
                'action' => __('The hotel prompt pack is not available for this workspace.'),
            ]);
        }

        Gate::authorize('view', $template);

        $context = $this->contexts->forAction($action, $payload);
        $prompt = $this->renderer->render($template, ['context' => $context]);

        $maxTokens = isset($payload['max_tokens']) && is_numeric($payload['max_tokens'])
            ? (int) $payload['max_tokens']
            : 1024;

        $model = isset($payload['model']) && is_string($payload['model']) && $payload['model'] !== ''
            ? $payload['model']
            : $template->model;

        return [
            new AiRequest(
                prompt: $prompt,
                system: 'You assist hotel staff. Be accurate, concise, and never invent guest facts.',
                model: $model,
                maxTokens: $maxTokens,
            ),
            $template,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function generate(string $action, array $payload, User $user, Company $company, ?string $provider = null): AiGeneration
    {
        [$request, $template] = $this->prepare($action, $payload, $company);

        return $this->generations->generate($request, $user, $provider ?? $template->provider, $template);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return Generator<int, string, void, AiGeneration>
     */
    public function stream(string $action, array $payload, User $user, Company $company, ?string $provider = null): Generator
    {
        [$request, $template] = $this->prepare($action, $payload, $company);

        return $this->generations->stream($request, $user, $provider ?? $template->provider, $template);
    }
}
