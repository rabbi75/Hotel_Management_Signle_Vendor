<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\DTOs\CouponData;
use App\Modules\Billing\Enums\CouponType;
use App\Modules\Billing\Http\Requests\StoreCouponRequest;
use App\Modules\Billing\Http\Requests\UpdateCouponRequest;
use App\Modules\Billing\Http\Resources\CouponResource;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Plan;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function __construct(protected SecurityLogger $security) {}

    public function index(Request $request): Response
    {
        abort_if($request->user('admin')?->cannot('platform.coupons.manage') ?? true, 403);

        $table = TableBuilder::for(Coupon::query(), $request, 'coupons')
            ->columns([
                Column::make('code', __('Code'))->sortable()->searchable()->locked(),
                Column::make('type', __('Type'))->sortable(),
                Column::make('value_formatted', __('Discount'))->align('right'),
                Column::make('redeemed_count', __('Redeemed'))->sortable()->align('right'),
                Column::make('max_redemptions', __('Limit'))->sortable()->align('right'),
                Column::make('expires_at', __('Expires'))->sortable(),
                Column::make('is_active', __('Active'))->sortable(),
            ])
            ->filters([
                Filter::make('type', __('Type'))->fromEnum(CouponType::class),
                Filter::make('is_active', __('Active'))->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->transform(fn (Coupon $coupon): array => (new CouponResource($coupon))->resolve($request));

        return Inertia::render('admin/coupons/index', [
            'table' => $table->toArray(),
            'plans' => Plan::query()
                ->orderBy('sort')
                ->get()
                ->map(fn (Plan $plan): array => ['value' => $plan->id, 'label' => $plan->name])
                ->values()
                ->all(),
            'can' => [
                'manage' => $request->user('admin')?->can('platform.coupons.manage') ?? false,
            ],
        ]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $data = CouponData::fromRequest($request);

        $coupon = Coupon::query()->create($data->toAttributes());

        $this->security->log(
            SecurityEvent::CouponCreated,
            $request->user(),
            __('Coupon :code created.', ['code' => $coupon->code]),
            ['coupon_id' => $coupon->id],
        );

        return back()->with('success', __('Coupon :code created.', ['code' => $coupon->code]));
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $data = CouponData::fromRequest($request);

        $coupon->fill($data->toUpdateAttributes())->save();

        $this->security->log(
            SecurityEvent::CouponUpdated,
            $request->user(),
            __('Coupon :code updated.', ['code' => $coupon->code]),
            ['coupon_id' => $coupon->id, 'fields' => $data->provided],
        );

        return back()->with('success', __('Coupon updated.'));
    }

    public function destroy(Request $request, Coupon $coupon): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.coupons.manage') ?? true, 403);

        $code = $coupon->code;
        $coupon->delete();

        $this->security->log(
            SecurityEvent::CouponDeleted,
            $request->user(),
            __('Coupon :code deleted.', ['code' => $code]),
            ['code' => $code],
        );

        return back()->with('success', __('Coupon deleted.'));
    }
}
