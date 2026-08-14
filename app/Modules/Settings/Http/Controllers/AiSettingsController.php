<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\AI\DTOs\AiRequest;
use App\Modules\AI\Services\ProviderManager;
use App\Modules\Settings\Actions\UpdateSettings;
use App\Modules\Settings\DTOs\AiSettingsData;
use App\Modules\Settings\Http\Requests\UpdateAiSettingsRequest;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Support\SettingsSchema;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Throwable;

/**
 * Platform-wide LLM credentials and tenant AI policy.
 *
 * Secrets are encrypted at rest and only ever sent to the client as
 * {@see SettingsSchema::MASK} plus an `is_set` flag.
 */
class AiSettingsController extends SettingsController
{
    public function __construct(
        SettingsRepository $settings,
        UpdateSettings $updateSettings,
        protected ProviderManager $providers,
    ) {
        parent::__construct($settings, $updateSettings);
    }

    public function index(): Response
    {
        return $this->panel('admin/settings/ai', SettingsSchema::GROUP_AI, [
            'providers' => $this->providers->catalogue(),
            'mask' => SettingsSchema::MASK,
        ]);
    }

    public function update(UpdateAiSettingsRequest $request): RedirectResponse
    {
        return $this->persist(AiSettingsData::fromRequest($request), $request);
    }

    public function test(string $provider): JsonResponse
    {
        Gate::authorize('manageAi', Setting::class);

        if (! $this->providers->supports($provider)) {
            return new JsonResponse(['ok' => false, 'message' => __('Unknown provider.')], 422);
        }

        try {
            $driver = $this->providers->driver($provider);
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
