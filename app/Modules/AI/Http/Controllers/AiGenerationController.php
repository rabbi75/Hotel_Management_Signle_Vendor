<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Actions\EnsureHotelPromptPack;
use App\Modules\AI\DTOs\AiRequest;
use App\Modules\AI\Http\Requests\GenerateRequest;
use App\Modules\AI\Http\Resources\AiGenerationResource;
use App\Modules\AI\Http\Resources\AiPromptTemplateResource;
use App\Modules\AI\Models\AiGeneration;
use App\Modules\AI\Models\AiPromptTemplate;
use App\Modules\AI\Services\CreditManager;
use App\Modules\AI\Services\GenerationService;
use App\Modules\AI\Services\ProviderManager;
use App\Modules\AI\Services\TemplateRenderer;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AiGenerationController extends Controller
{
    public function __construct(
        protected ProviderManager $providers,
        protected GenerationService $generations,
        protected CreditManager $credits,
        protected TemplateRenderer $renderer,
        protected EnsureHotelPromptPack $ensurePack,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('create', AiGeneration::class);

        $company = current_company();

        if ($company instanceof Company) {
            $this->ensurePack->handle($company);
        }

        $balance = $this->credits->balance();

        return Inertia::render('ai/index', [
            'providers' => $this->providers->catalogue(),
            'default_provider' => $this->providers->default(),
            'templates' => AiPromptTemplateResource::collection($this->visibleTemplates($request))->resolve($request),
            'credits' => [
                'enabled' => (bool) config('saas.ai.credits.enabled'),
                'allowance' => $balance->allowance,
                'used' => $balance->used,
                'reserved' => $balance->reserved,
                'available' => $balance->available(),
                'period' => $balance->period,
            ],
            'defaults' => [
                'max_tokens' => (int) config('saas.ai.max_tokens'),
            ],
            'recent' => AiGenerationResource::collection(
                AiGeneration::query()->latest('id')->limit(5)->get(),
            )->resolve($request),
        ]);
    }

    public function store(GenerateRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        [$aiRequest, $template, $provider] = $this->build($request);

        try {
            $generation = $this->generations->generate($aiRequest, $user, $provider, $template);
        } catch (Throwable $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }

        return new JsonResponse(['generation' => (new AiGenerationResource($generation))->resolve($request)]);
    }

    /**
     * Server-sent events. Streaming is not a nicety here: a large `max_tokens`
     * on a blocking request is the standard way to hit an HTTP timeout partway
     * through a generation the customer has already paid for.
     */
    public function stream(GenerateRequest $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        [$aiRequest, $template, $provider] = $this->build($request);

        return response()->stream(function () use ($aiRequest, $user, $provider, $template): void {
            $emit = static function (string $event, mixed $data): void {
                echo 'event: '.$event."\n";
                echo 'data: '.json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            try {
                $stream = $this->generations->stream($aiRequest, $user, $provider, $template);

                foreach ($stream as $chunk) {
                    $emit('delta', ['text' => $chunk]);

                    if (connection_aborted() === 1) {
                        return;
                    }
                }

                $generation = $stream->getReturn();

                $emit('done', ['generation' => (new AiGenerationResource($generation))->resolve(request())]);
            } catch (Throwable $exception) {
                $emit('error', ['message' => $exception->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * @return array{0: AiRequest, 1: AiPromptTemplate|null, 2: string|null}
     */
    protected function build(GenerateRequest $request): array
    {
        $template = null;
        $prompt = $request->string('prompt')->toString();
        $provider = $request->string('provider')->toString() ?: null;
        $model = $request->string('model')->toString() ?: null;

        $templateId = $request->input('template_id');

        if (is_numeric($templateId)) {
            $template = AiPromptTemplate::query()->findOrFail((int) $templateId);
            Gate::authorize('view', $template);

            /** @var array<string, mixed> $variables */
            $variables = (array) $request->input('variables', []);
            $prompt = $this->renderer->render($template, $variables);

            $provider ??= $template->provider;
            $model ??= $template->model;
        }

        $maxTokens = $request->input('max_tokens');
        $temperature = $request->input('temperature');

        return [
            new AiRequest(
                prompt: $prompt,
                system: $request->string('system')->toString() ?: null,
                model: $model,
                maxTokens: is_numeric($maxTokens) ? (int) $maxTokens : null,
                temperature: is_numeric($temperature) ? (float) $temperature : null,
            ),
            $template,
            $provider,
        ];
    }

    /**
     * @return Collection<int, AiPromptTemplate>
     */
    protected function visibleTemplates(Request $request)
    {
        $user = $request->user();
        $userId = $user?->getAuthIdentifier();

        return AiPromptTemplate::query()
            ->where(fn ($query) => $query->where('is_shared', true)->orWhere('user_id', $userId))
            ->orderBy('name')
            ->get();
    }
}
