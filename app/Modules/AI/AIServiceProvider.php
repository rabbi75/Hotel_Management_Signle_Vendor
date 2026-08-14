<?php

declare(strict_types=1);

namespace App\Modules\AI;

use App\Modules\AI\Listeners\SeedHotelPromptPack;
use App\Modules\AI\Models\AiCreditBalance;
use App\Modules\AI\Models\AiGeneration;
use App\Modules\AI\Models\AiPromptTemplate;
use App\Modules\AI\Policies\AiCreditBalancePolicy;
use App\Modules\AI\Policies\AiGenerationPolicy;
use App\Modules\AI\Policies\AiPromptTemplatePolicy;
use App\Modules\AI\Services\CreditManager;
use App\Modules\AI\Services\HotelAiAssistService;
use App\Modules\AI\Services\HotelAiContextBuilder;
use App\Modules\AI\Services\ProviderKeyStore;
use App\Modules\AI\Services\ProviderManager;
use App\Modules\AI\Support\AnthropicClientFactory;
use App\Modules\Api\Support\WebhookEventRegistry;
use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\Company\Events\CompanyCreated;
use App\Modules\Company\Models\Company;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;
use Illuminate\Support\Facades\Event;

class AIServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        AiPromptTemplate::class => AiPromptTemplatePolicy::class,
        AiGeneration::class => AiGenerationPolicy::class,
        AiCreditBalance::class => AiCreditBalancePolicy::class,
    ];

    protected function registerModule(): void
    {
        $this->app->singleton(ProviderManager::class);
        $this->app->singleton(ProviderKeyStore::class);
        $this->app->singleton(CreditManager::class);
        $this->app->singleton(HotelAiContextBuilder::class);
        $this->app->singleton(HotelAiAssistService::class);

        // Bound so a test can supply a client whose PSR-18 transporter is a
        // fake; production always gets the real SDK client.
        $this->app->singleton(AnthropicClientFactory::class);
    }

    protected function bootModule(): void
    {
        $this->registerLimitResolvers();
        $this->registerNavigation();
        $this->registerWebhookEvents();

        Event::listen(CompanyCreated::class, SeedHotelPromptPack::class);
    }

    /**
     * Report AI credits consumed this period to the usage meters, so a plan's
     * `ai_credits` ceiling reads the same figure the CreditManager enforces.
     */
    protected function registerLimitResolvers(): void
    {
        SubscriptionLimits::resolveUsing(
            'ai_credits',
            static fn (Company $company): int => (int) AiCreditBalance::query()
                ->forCompany($company->id)
                ->where('period', AiCreditBalance::currentPeriod())
                ->sum('used'),
        );
    }

    protected function registerNavigation(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Assistants', 34)->items([
                NavigationItem::make('Daily brief', 'ai.brief.index')
                    ->icon('sparkles')
                    ->permissions('ai.use')
                    ->feature('ai')
                    ->activeWhen('ai.brief.*')
                    ->order(10),
                NavigationItem::make('Templates', 'ai.templates.index')
                    ->icon('file-text')
                    ->permissions('ai.use')
                    ->feature('ai')
                    ->activeWhen('ai.templates.*')
                    ->order(20),
                NavigationItem::make('Usage', 'ai.credits.index')
                    ->icon('wallet')
                    ->permissions('ai.use')
                    ->feature('ai')
                    ->activeWhen('ai.credits.*', 'ai.history.*')
                    ->order(30),
            ]),
        );
    }

    /**
     * Published to webhook subscribers when the API module is present.
     */
    protected function registerWebhookEvents(): void
    {
        if (! class_exists(WebhookEventRegistry::class)) {
            return;
        }

        $this->app->make(WebhookEventRegistry::class)->registerMany('AI', [
            'ai.generation_completed' => __('An AI generation finished.'),
            'ai.credits_exhausted' => __('A workspace ran out of AI credits.'),
        ]);
    }
}
