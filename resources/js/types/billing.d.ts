/*
|------------------------------------------------------------------------------
| Billing
|------------------------------------------------------------------------------
|
| Mirrors App\Modules\Billing\Http\Resources\* and the props the billing
| controllers attach alongside them.
|
| Every amount is in integer minor units. The `*_formatted` twin is what a
| screen renders — nothing on the client divides by 100.
|
*/

export type BillingIntervalValue = 'monthly' | 'yearly';

export type SubscriptionStatusValue = 'trialing' | 'active' | 'past_due' | 'canceled' | 'incomplete' | 'expired';

export type InvoiceStatusValue = 'draft' | 'open' | 'paid' | 'void' | 'uncollectible' | 'refunded';

export type CouponTypeValue = 'percent' | 'fixed';

export interface Plan {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    features: string[];
    entitlements: string[];
    limits: Record<string, number>;
    monthly_price: number;
    yearly_price: number;
    monthly_price_formatted: string;
    yearly_price_formatted: string;
    yearly_savings: number;
    yearly_savings_formatted: string;
    currency: string;
    trial_days: number;
    is_active: boolean;
    is_public: boolean;
    is_free: boolean;
    sort: number;
    gateway_prices: Record<string, Record<string, string>>;
    subscribers_count: number | null;
    created_at: string | null;
}

export interface Subscription {
    id: number;
    plan_id: number;
    plan: Plan | null;
    gateway: string;
    status: SubscriptionStatusValue;
    status_label: string;
    status_color: string;
    interval: BillingIntervalValue;
    interval_label: string;
    quantity: number;
    price: number | null;
    price_formatted: string | null;
    on_trial: boolean;
    is_cancelling: boolean;
    grants_access: boolean;
    trial_ends_at: string | null;
    current_period_start: string | null;
    current_period_end: string | null;
    cancels_at: string | null;
    ended_at: string | null;
    created_at: string | null;
}

export interface InvoiceLine {
    id: number;
    description: string;
    quantity: number;
    unit_amount: number;
    unit_amount_formatted: string;
    amount: number;
    amount_formatted: string;
    period: string | null;
}

export interface Invoice {
    id: number;
    number: string;
    status: InvoiceStatusValue;
    status_label: string;
    status_color: string;
    subtotal: number;
    tax: number;
    discount: number;
    total: number;
    currency: string;
    subtotal_formatted: string;
    tax_formatted: string;
    discount_formatted: string;
    total_formatted: string;
    issued_at: string | null;
    due_at: string | null;
    paid_at: string | null;
    plan: string | null;
    lines: InvoiceLine[];
    created_at: string | null;

    /** Only populated by the operator console, where the listing spans tenants. */
    company: string | null;
    company_uuid: string | null;
}

export interface Coupon {
    id: number;
    code: string;
    description: string | null;
    type: CouponTypeValue;
    type_label: string;
    value: number;
    value_formatted: string;
    currency: string | null;
    max_redemptions: number | null;
    redeemed_count: number;
    expires_at: string | null;
    plan_ids: number[];
    is_active: boolean;
    is_expired: boolean;
    is_exhausted: boolean;
    is_redeemable: boolean;
    created_at: string | null;
}

export interface PaymentMethod {
    id: number;
    gateway: string;
    type: string;
    brand: string | null;
    last_four: string | null;
    exp_month: number | null;
    exp_year: number | null;
    holder_name: string | null;
    is_default: boolean;
    is_expired: boolean;
    created_at: string | null;
}

/** One plan-limit meter, as SubscriptionLimits::meters() shapes it. */
export interface UsageMeter {
    key: string;
    label: string;
    /** -1 means unlimited. */
    limit: number;
    used: number;
    remaining: number;
    /** Null when the limit is unlimited or zero. */
    percentage: number | null;
}

export interface NextInvoice {
    amount: number;
    amount_formatted: string;
    date: string | null;
}

/** Keyed `{plan-slug}:{interval}`, as SubscriptionController::previews() builds it. */
export interface ProrationPreview {
    credit: number;
    charge: number;
    due: number;
    due_formatted: string;
    prorated: boolean;
}

export interface PlanOption {
    value: number;
    label: string;
}

/*
|------------------------------------------------------------------------------
| Operator console
|------------------------------------------------------------------------------
|
| Props for the platform billing screens. `MoneyValue` mirrors
| App\Modules\Billing\Support\Money::toArray() — the minor units are there for
| sorting and charting, `formatted` is the only thing rendered.
|
*/

export interface MoneyValue {
    amount: number;
    currency: string;
    formatted: string;
}

export interface RevenueTrendPoint {
    month: string;
    collected: MoneyValue;
}

export interface ChurnPoint {
    month: string;
    ended: number;
    base: number;
    rate: number;
}

export interface PlanMixRow {
    plan: string;
    accounts: number;
    mrr: MoneyValue;
}

export interface RevenueOverview {
    mrr: MoneyValue;
    arr: MoneyValue;
    arpa: MoneyValue;
    paying_accounts: number;
    trialing_accounts: number;
    collected_this_month: MoneyValue;
    outstanding: MoneyValue;
    failed_payments: number;
    trend: RevenueTrendPoint[];
    plan_mix: PlanMixRow[];
    churn: ChurnPoint[];
}

export interface PastDueRow {
    id: number;
    company: string;
    company_uuid: string;
    plan: string;
    mrr: MoneyValue;
    past_due_since: string | null;
    dunning_attempts: number;
    /** Days before access is revoked; negative once the grace window has lapsed. */
    days_left: number | null;
}

export interface OverdueInvoiceRow {
    id: number;
    number: string;
    company: string;
    total: MoneyValue;
    due_at: string | null;
    days_overdue: number;
}

export interface FailedPaymentRow {
    id: number;
    company: string;
    invoice: string | null;
    amount: MoneyValue;
    reason: string | null;
    failed_at: string | null;
}

export interface WebhookEventRow {
    id: number;
    gateway: string;
    event_id: string;
    type: string;
    processed: boolean;
    processed_at: string | null;
    created_at: string | null;
    payload: Record<string, unknown> | null;
}

/*
|------------------------------------------------------------------------------
| Payment gateways
|------------------------------------------------------------------------------
|
| Mirrors PlatformGatewayController::present(). A secret credential is never
| sent: `is_set` says whether one is stored, and `value` carries the mask.
|
*/

export interface GatewayCredentialField {
    label: string;
    secret: boolean;
    help: string | null;
    is_set: boolean;
    /** The mask for a stored secret; the real value for a non-secret field. */
    value: string;
}

export interface GatewayActivity {
    transactions: number;
    failures: number;
    events: number;
    unprocessed: number;
}

export interface PaymentGatewayRow {
    id: number;
    driver: string;
    label: string;
    description: string;
    is_enabled: boolean;
    is_test_mode: boolean;
    /** Every declared credential has a value, so it may be offered at checkout. */
    is_ready: boolean;
    supports_refunds: boolean;
    sort: number;
    currencies: string[];
    countries: string[];
    credentials: Record<string, GatewayCredentialField>;
    last_tested_at: string | null;
    last_test_error: string | null;
    recent: GatewayActivity;
}

/** One processor a customer may pick at checkout, as the plan picker sees it. */
export interface CheckoutGatewayOption {
    driver: string;
    label: string;
    test_mode: boolean;
}
