<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Enums\CreditTransactionType;
use App\Modules\AI\Http\Requests\AdjustCreditsRequest;
use App\Modules\AI\Http\Resources\AiCreditTransactionResource;
use App\Modules\AI\Models\AiCreditBalance;
use App\Modules\AI\Models\AiCreditTransaction;
use App\Modules\AI\Services\CreditManager;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AiCreditController extends Controller
{
    public function __construct(
        protected CreditManager $credits,
        protected SecurityLogger $security,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AiCreditBalance::class);

        $balance = $this->credits->balance();

        $ledger = TableBuilder::for(AiCreditTransaction::query()->with('user'), $request, 'ledger')
            ->columns([
                Column::make('created_at', __('When'))->sortable()->locked(),
                Column::make('type_label', __('Type'))->locked(),
                Column::make('credits', __('Credits'))->sortable('credits')->align('right'),
                Column::make('description', __('Note'))->searchable('description'),
                Column::make('user', __('User')),
                Column::make('period', __('Period'))->sortable()->hidden(),
            ])
            ->filters([
                Filter::make('type', __('Type'))->fromEnum(CreditTransactionType::class),
                Filter::make('created_at', __('Date'))->dateRange(),
            ])
            ->defaultSort('created_at')
            ->transform(fn (AiCreditTransaction $transaction): array => (new AiCreditTransactionResource($transaction))->resolve($request));

        return Inertia::render('ai/credits', [
            'balance' => [
                'enabled' => (bool) config('saas.ai.credits.enabled'),
                'period' => $balance->period,
                'allowance' => $balance->allowance,
                'used' => $balance->used,
                'reserved' => $balance->reserved,
                'available' => $balance->available(),
            ],
            'rates' => [
                'per_1k_input' => (int) config('saas.ai.credits.per_1k_input'),
                'per_1k_output' => (int) config('saas.ai.credits.per_1k_output'),
            ],
            'ledger' => $ledger->toArray(),
            'can' => ['manage' => Gate::allows('manage', AiCreditBalance::class)],
        ]);
    }

    public function adjust(AdjustCreditsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $credits = (int) $request->integer('credits');
        $reason = $request->string('reason')->toString();

        $this->credits->adjust(
            (int) current_company_id(),
            $credits,
            $user?->getAuthIdentifier() === null ? null : (int) $user->getAuthIdentifier(),
            $reason,
        );

        $this->security->log(SecurityEvent::AiCreditsAdjusted, $user, __('AI credit allowance adjusted.'), [
            'credits' => $credits,
            'reason' => $reason,
        ]);

        return back()->with('success', __('Credit allowance adjusted.'));
    }
}
