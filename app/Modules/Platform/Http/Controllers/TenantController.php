<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\Company;
use App\Modules\Platform\Http\Resources\TenantResource;
use App\Modules\Platform\Models\CompanySupportNote;
use App\Modules\Platform\Services\TenantOpsSummary;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\SupportTicket;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every workspace in the installation.
 *
 * The route group runs without SetCurrentCompany, so these queries are already
 * unscoped. Anything that needs to behave *as* a tenant — usage meters, the
 * activity feed — is wrapped in {@see CurrentCompany::scopeTo()} so the existing
 * tenant-scoped services keep working unchanged.
 */
class TenantController extends Controller
{
    public function __construct(
        protected CurrentCompany $tenant,
        protected SubscriptionLimits $limits,
        protected SecurityLogger $security,
        protected TenantOpsSummary $ops,
    ) {}

    public function index(Request $request): Response
    {
        abort_if($request->user('admin')?->cannot('platform.tenants.view') ?? true, 403);

        return Inertia::render('admin/tenants/index', [
            'table' => $this->table($request)->toArray(),
            'plans' => $this->planOptions(),
            'can' => [
                'manage' => $request->user('admin')?->can('platform.tenants.manage') ?? false,
                'impersonate' => $request->user('admin')?->can('platform.tenants.impersonate') ?? false,
            ],
        ]);
    }

    public function show(Request $request, Company $company): Response
    {
        abort_if($request->user('admin')?->cannot('platform.tenants.view') ?? true, 403);

        $company->load(['owner', 'activeSubscription.plan', 'departments', 'teams']);

        return Inertia::render('admin/tenants/show', [
            'tenant' => (new TenantResource($company))->resolve($request),
            'members' => $this->members($company),
            'meters' => $this->tenant->scopeTo($company, fn (): array => $this->limits->meters($company)),
            'subscriptions' => $this->tenant->scopeTo($company, static fn (): array => $company
                ->subscriptions()
                ->with('plan')
                ->limit(10)
                ->get()
                ->map(static fn ($subscription): array => [
                    'id' => $subscription->id,
                    'plan' => $subscription->plan->name,
                    'status' => $subscription->status->value,
                    'interval' => $subscription->interval->value,
                    'gateway' => $subscription->gateway,
                    'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                    'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                    'ended_at' => $subscription->ended_at?->toIso8601String(),
                ])
                ->all()),
            'plans' => $this->planOptions(),
            'counts' => [
                'members' => $company->members()->count(),
                'departments' => $company->departments()->count(),
                'teams' => $company->teams()->count(),
                'open_tickets' => SupportTicket::query()
                    ->withoutCompanyScope()
                    ->where('company_id', $company->id)
                    ->whereIn('status', [
                        TicketStatus::Open->value,
                        TicketStatus::AwaitingSupport->value,
                        TicketStatus::AwaitingCustomer->value,
                    ])
                    ->count(),
            ],
            'hotel' => $this->ops->hotelFootprint($company),
            'ai' => $this->ops->aiSummary($company),
            'support_notes' => CompanySupportNote::query()
                ->with('admin:id,name')
                ->where('company_id', $company->id)
                ->orderByDesc('is_pinned')
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(static fn (CompanySupportNote $note): array => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'is_pinned' => $note->is_pinned,
                    'admin' => $note->admin?->name,
                    'created_at' => $note->created_at?->toIso8601String(),
                ])
                ->all(),
            'can' => [
                'manage' => $request->user('admin')?->can('platform.tenants.manage') ?? false,
                'impersonate' => $request->user('admin')?->can('platform.tenants.impersonate') ?? false,
            ],
        ]);
    }

    /**
     * Suspend or restore a workspace.
     *
     * Suspension is a property of the workspace rather than of its members: the
     * owner keeps their account and their other workspaces, and SetCurrentCompany
     * refuses to resolve this one until it is restored.
     */
    public function updateStatus(Request $request, Company $company): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.tenants.manage') ?? true, 403);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $active = (bool) $validated['is_active'];

        $company->forceFill(['is_active' => $active])->save();

        $this->security->log(
            $active ? SecurityEvent::TenantRestored : SecurityEvent::TenantSuspended,
            $request->user('admin'),
            $active
                ? __('Restored workspace :name.', ['name' => $company->name])
                : __('Suspended workspace :name.', ['name' => $company->name]),
            ['company_id' => $company->id, 'reason' => $validated['reason'] ?? null],
        );

        return back()->with('success', $active
            ? __('Workspace restored.')
            : __('Workspace suspended.'));
    }

    /**
     * @return TableBuilder<Company>
     */
    protected function table(Request $request): TableBuilder
    {
        $builder = TableBuilder::for($this->query(), $request)
            ->columns([
                Column::make('name', __('Workspace'))->searchable()->sortable()->locked(),
                Column::make('owner', __('Owner')),
                Column::make('plan', __('Plan')),
                Column::make('subscription_status', __('Subscription')),
                Column::make('members_count', __('Members'))->align('right'),
                Column::make('is_active', __('Status'))->sortable(),
                Column::make('created_at', __('Created'))->sortable(),
            ])
            ->filters([
                Filter::make('is_active', __('Status'))->options([
                    '1' => __('Active'),
                    '0' => __('Suspended'),
                ]),
                Filter::make('plan', __('Plan'))
                    ->options($this->planOptions())
                    ->multiple()
                    ->using(static function (Builder $query, mixed $value): void {
                        $query->whereHas(
                            'activeSubscription.plan',
                            static fn (Builder $plans) => $plans->whereIn('slug', (array) $value),
                        );
                    }),
                Filter::make('created_at', __('Created'))->dateRange(),
            ])
            ->defaultSort('created_at')
            ->transform(fn (Company $company): array => (new TenantResource($company))->resolve($request));

        return $builder;
    }

    /**
     * @return Builder<Company>
     */
    protected function query(): Builder
    {
        return Company::query()
            ->with(['owner', 'activeSubscription.plan'])
            ->withCount('members');
    }

    /**
     * @return array<string, string>
     */
    protected function planOptions(): array
    {
        /** @var array<string, string> $options */
        $options = Plan::query()->orderBy('sort')->pluck('name', 'slug')->all();

        return $options;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function members(Company $company): array
    {
        return $company->members()
            ->limit(50)
            ->get()
            ->map(static function ($user): array {
                $role = $user->pivot->role;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $role instanceof CompanyRole
                        ? $role->value
                        : CompanyRole::tryFrom((string) $role)?->value,
                    'status' => $user->status->value,
                    'joined_at' => $user->pivot->joined_at
                        ? (string) $user->pivot->joined_at
                        : null,
                    'avatar' => method_exists($user, 'avatarUrl') ? $user->avatarUrl() : null,
                    'initials' => method_exists($user, 'initials') ? $user->initials() : null,
                ];
            })
            ->all();
    }
}
