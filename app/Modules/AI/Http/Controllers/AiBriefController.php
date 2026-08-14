<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Actions\EnsureHotelPromptPack;
use App\Modules\AI\Models\AiGeneration;
use App\Modules\AI\Services\CreditManager;
use App\Modules\AI\Services\ProviderManager;
use App\Modules\AI\Support\HotelPromptPack;
use App\Modules\Company\Models\Company;
use App\Modules\Hotel\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AiBriefController extends Controller
{
    public function __construct(
        protected EnsureHotelPromptPack $ensurePack,
        protected CreditManager $credits,
        protected ProviderManager $providers,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('create', AiGeneration::class);

        /** @var Company $company */
        $company = current_company();
        $this->ensurePack->handle($company);

        $balance = $this->credits->balance();

        $hotels = Hotel::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(static fn (Hotel $hotel): array => [(string) $hotel->id => $hotel->name])
            ->all();

        return Inertia::render('ai/brief', [
            'actions' => [
                [
                    'action' => 'gm.daily_brief',
                    'label' => 'GM daily brief',
                    'description' => 'Occupancy, arrivals, revenue, housekeeping and maintenance narrative for today.',
                ],
                [
                    'action' => 'housekeeping.floor_readiness',
                    'label' => 'Housekeeping readiness',
                    'description' => 'Prioritise open cleaning tasks for the current shift.',
                ],
            ],
            'templates' => collect(HotelPromptPack::definitions())
                ->map(static fn (array $definition): array => [
                    'slug' => $definition['slug'],
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                ])
                ->values()
                ->all(),
            'hotels' => $hotels,
            'selected_hotel_id' => current_hotel_id(),
            'default_provider' => $this->providers->default(),
            'credits' => [
                'enabled' => (bool) config('saas.ai.credits.enabled'),
                'allowance' => $balance->allowance,
                'used' => $balance->used,
                'reserved' => $balance->reserved,
                'available' => $balance->available(),
                'period' => $balance->period,
            ],
        ]);
    }
}
