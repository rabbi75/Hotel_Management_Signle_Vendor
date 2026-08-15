<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\DTOs\PlanData;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Http\Requests\StorePlanRequest;
use App\Modules\Billing\Http\Requests\UpdatePlanRequest;
use App\Modules\Billing\Http\Resources\PlanResource;
use App\Modules\Billing\Models\Plan;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administration of the plan catalogue.
 */
class PlanController extends Controller
{
    public function __construct(
        protected SecurityLogger $security,
        protected GatewayManager $gateways,
    ) {}

    public function index(Request $request): Response
    {
        abort_if($request->user('admin')?->cannot('platform.plans.manage') ?? true, 403);

        $query = Plan::query()->withCount('subscriptions');

        $table = TableBuilder::for($query, $request, 'plans')
            ->columns([
                Column::make('name', __('Plan'))->sortable()->searchable()->locked(),
                Column::make('monthly_price', __('Monthly'))->sortable()->align('right'),
                Column::make('yearly_price', __('Yearly'))->sortable()->align('right'),
                Column::make('trial_days', __('Trial'))->sortable()->align('right'),
                Column::make('subscribers_count', __('Subscribers'))->align('right'),
                Column::make('is_active', __('Active'))->sortable(),
                Column::make('sort', __('Order'))->sortable()->hidden(),
            ])
            ->filters([
                Filter::make('is_active', __('Active'))->boolean(),
            ])
            ->defaultSort('sort', 'desc')
            ->transform(fn (Plan $plan): array => (new PlanResource($plan))->resolve($request));

        return Inertia::render('admin/plans/index', [
            'table' => $table->toArray(),
            'gateways' => $this->gateways->available(),
            'entitlements' => (array) config('entitlements.features', []),
            'can' => [
                'manage' => $request->user('admin')?->can('platform.plans.manage') ?? false,
            ],
        ]);
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        $data = PlanData::fromRequest($request);

        $plan = new Plan($data->toAttributes());
        $plan->slug = $this->uniqueSlug($data->slug ?? $data->name);
        $plan->save();

        $this->security->log(
            SecurityEvent::PlanCreated,
            $request->user(),
            __('Plan :name created.', ['name' => $plan->name]),
            ['plan_id' => $plan->id],
        );

        return back()->with('success', __('Plan :name created.', ['name' => $plan->name]));
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $data = PlanData::fromRequest($request);

        $attributes = $data->toUpdateAttributes();

        // A blank slug on an edit means "keep the current one", not "regenerate":
        // slugs are in URLs customers may already have bookmarked.
        if (($attributes['slug'] ?? null) === null) {
            unset($attributes['slug']);
        }

        $plan->fill($attributes)->save();

        $this->security->log(
            SecurityEvent::PlanUpdated,
            $request->user(),
            __('Plan :name updated.', ['name' => $plan->name]),
            ['plan_id' => $plan->id, 'fields' => $data->provided],
        );

        return back()->with('success', __('Plan updated.'));
    }

    public function destroy(Request $request, Plan $plan): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.plans.manage') ?? true, 403);

        if ($plan->subscriptions()->exists()) {
            return back()->with('error', __('This plan still has subscribers and cannot be deleted. Deactivate it instead.'));
        }

        $plan->delete();

        $this->security->log(
            SecurityEvent::PlanDeleted,
            $request->user(),
            __('Plan :name deleted.', ['name' => $plan->name]),
            ['plan_id' => $plan->id],
        );

        return back()->with('success', __('Plan deleted.'));
    }

    protected function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 1;

        while ($this->slugTaken($slug, $ignoreId)) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }

    protected function slugTaken(string $slug, ?int $ignoreId): bool
    {
        $query = Plan::query()->withTrashed()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
