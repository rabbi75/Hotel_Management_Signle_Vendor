<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\DTOs\AiRequest;
use App\Modules\AI\Http\Requests\UpdateProviderKeysRequest;
use App\Modules\AI\Services\ProviderKeyStore;
use App\Modules\AI\Services\ProviderManager;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AiProviderController extends Controller
{
    public function __construct(
        protected ProviderManager $providers,
        protected ProviderKeyStore $keys,
        protected SecurityLogger $security,
    ) {}

    public function index(): Response
    {
        Gate::authorize('ai.providers.manage');

        return Inertia::render('ai/providers', [
            'providers' => $this->providers->catalogue(),
            // The key itself never crosses this boundary: the client gets a
            // fixed mask and a boolean, and submitting the mask back is a no-op.
            'mask' => ProviderKeyStore::MASK,
            'default_provider' => $this->providers->default(),
            'allow_tenant_keys' => $this->keys->tenantKeysAllowed(),
        ]);
    }

    public function update(UpdateProviderKeysRequest $request): RedirectResponse
    {
        if (! $this->keys->tenantKeysAllowed()) {
            return back()->with('error', __('Platform administrators have disabled custom provider keys.'));
        }
        /** @var array<string, mixed> $keys */
        $keys = (array) $request->input('keys', []);
        $changed = [];

        foreach ($this->providers->keys() as $provider) {
            if (! array_key_exists($provider, $keys)) {
                continue;
            }

            $value = $keys[$provider];

            if (! is_string($value) || $value === '' || $value === ProviderKeyStore::MASK) {
                continue;
            }

            $this->keys->put($provider, $value);
            $changed[] = $provider;
        }

        if ($changed !== []) {
            // The key itself is never logged — only which provider changed.
            $this->security->log(
                SecurityEvent::AiProviderConfigured,
                $request->user(),
                __('AI provider credentials updated.'),
                ['providers' => $changed],
            );
        }

        return back()->with('success', __('Provider credentials saved.'));
    }

    public function test(string $provider): JsonResponse
    {
        Gate::authorize('ai.providers.manage');

        if (! $this->providers->supports($provider)) {
            return new JsonResponse(['ok' => false, 'message' => __('Unknown provider.')], 422);
        }

        try {
            $driver = $this->providers->driver($provider);

            // Deliberately tiny: this proves the credential and the endpoint,
            // and should not cost the customer a meaningful number of tokens.
            $response = $driver->generate(new AiRequest(prompt: 'Reply with the single word: ok.', maxTokens: 16));
        } catch (Throwable $exception) {
            return new JsonResponse(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return new JsonResponse([
            'ok' => true,
            'message' => __('Connection succeeded.'),
            'model' => $response->model,
            'tokens' => $response->totalTokens(),
        ]);
    }
}
