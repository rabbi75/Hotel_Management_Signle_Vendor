<?php

declare(strict_types=1);

namespace App\Modules\Billing;

use App\Modules\Billing\Console\BackfillSubscriptionsCommand;
use App\Modules\Billing\Console\RenewSubscriptionsCommand;
use App\Modules\Billing\Contracts\PaymentGateway;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentMethod;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Policies\CouponPolicy;
use App\Modules\Billing\Policies\InvoicePolicy;
use App\Modules\Billing\Policies\PaymentMethodPolicy;
use App\Modules\Billing\Policies\PlanPolicy;
use App\Modules\Billing\Policies\SubscriptionPolicy;
use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\Company\Enums\InvitationStatus;
use App\Modules\Company\Models\Company;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;

class BillingServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Plan::class => PlanPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
        Invoice::class => InvoicePolicy::class,
        Coupon::class => CouponPolicy::class,
        PaymentMethod::class => PaymentMethodPolicy::class,
    ];

    protected function registerModule(): void
    {
        $this->app->singleton(GatewayManager::class);
        $this->app->singleton(SubscriptionLimits::class);

        // Resolving the contract gives you whichever driver configuration
        // selects, so consumers never name a concrete gateway.
        $this->app->bind(
            PaymentGateway::class,
            static fn ($app): PaymentGateway => $app->make(GatewayManager::class)->driver(),
        );
    }

    protected function bootModule(): void
    {
        $this->loadViewsFrom($this->path('Resources/views'), 'billing');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RenewSubscriptionsCommand::class,
                BackfillSubscriptionsCommand::class,
            ]);
        }

        $this->registerLimitResolvers();
        $this->registerNavigation();
    }

    /**
     * Teach {@see SubscriptionLimits} how to measure the resources this module
     * cannot count on its own. Anything without a resolver falls back to the
     * usage ledger.
     */
    protected function registerLimitResolvers(): void
    {
        // A seat is occupied by a current member or a pending invitation, so
        // inviting up to the ceiling cannot be circumvented by leaving invites
        // outstanding.
        SubscriptionLimits::resolveUsing(
            'seats',
            static fn (Company $company): int => $company->members()->count()
                + $company->invitations()->where('status', InvitationStatus::Pending->value)->count(),
        );
    }

    protected function registerNavigation(): void
    {
        if (single_vendor()) {
            return;
        }

        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Billing', 70)->items([
                NavigationItem::make('Subscription', 'billing.index')
                    ->icon('credit-card')
                    ->permissions('billing.view')
                    ->activeWhen('billing.index', 'billing.plans')
                    ->order(10),

                NavigationItem::make('Invoices', 'billing.invoices.index')
                    ->icon('receipt')
                    ->permissions('billing.view')
                    ->activeWhen('billing.invoices.*')
                    ->order(20),

            ]),
        );
    }
}
