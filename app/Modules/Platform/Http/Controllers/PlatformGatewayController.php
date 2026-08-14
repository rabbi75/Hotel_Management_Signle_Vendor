<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\Enums\TransactionStatus;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Models\PaymentGatewayConfig;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Billing\Services\GatewayConfigRepository;
use App\Modules\Billing\Services\GatewayRegistry;
use App\Modules\Platform\Models\Admin;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Which payment processors this installation accepts.
 *
 * Gated on the `admin` guard rather than through `Gate::authorize`, for the
 * same reason the rest of the console is: the Billing policies are typed to a
 * tenant `User` and test the active workspace, neither of which exists here.
 *
 * A stored secret is never sent back to the browser. The screen learns only
 * whether each credential is set, and a field posted back as the mask means
 * "leave it alone" — the rule the AI provider keys and the settings panels
 * already follow.
 */
class PlatformGatewayController extends Controller
{
    /** What the client receives in place of a stored secret. */
    public const MASK = '••••••••';

    public function __construct(
        protected GatewayRegistry $registry,
        protected GatewayConfigRepository $configs,
        protected GatewayManager $gateways,
        protected SecurityLogger $security,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeOperator($request);

        $rows = $this->configs->all()
            ->map(fn (PaymentGatewayConfig $config): array => $this->present($config))
            ->values()
            ->all();

        return Inertia::render('admin/billing/gateways', [
            'gateways' => $rows,
            'currencies' => $this->currencyOptions(),
            'can' => [
                'manage' => $request->user('admin')?->can('platform.gateways.manage') ?? false,
            ],
        ]);
    }

    public function update(Request $request, PaymentGatewayConfig $gateway): RedirectResponse
    {
        $this->authorizeOperator($request);

        $definition = $this->registry->get($gateway->driver);

        abort_if($definition === null, 404);

        $validated = $request->validate([
            'is_enabled' => ['boolean'],
            'is_test_mode' => ['boolean'],
            'currencies' => ['array'],
            'currencies.*' => ['string', 'size:3'],
            'countries' => ['array'],
            'countries.*' => ['string', 'size:2'],
            'credentials' => ['array'],
            // Long enough for an RSA private key in PEM form — Nagad issues one
            // as a credential, and a 2048-bit block does not fit in 500. The
            // column is an encrypted text blob, so nothing downstream cares.
            'credentials.*' => ['nullable', 'string', 'max:4000'],
        ]);

        /** @var array<string, mixed> $submitted */
        $submitted = $validated['credentials'] ?? [];

        $gateway->fill([
            'is_enabled' => $request->boolean('is_enabled'),
            'is_test_mode' => $request->boolean('is_test_mode'),
            'currencies' => array_values(array_map(strtoupper(...), $validated['currencies'] ?? [])),
            'countries' => array_values(array_map(strtoupper(...), $validated['countries'] ?? [])),
            'credentials' => $this->mergeCredentials($gateway, $definition['credentials'], $submitted),
        ]);

        $changed = array_keys($gateway->getDirty());
        $gateway->save();
        $this->configs->flush();

        if ($changed !== []) {
            // Key names only. The values are processor credentials, and a
            // security log is not a place to put them.
            $this->security->log(
                SecurityEvent::PaymentGatewayConfigured,
                $request->user('admin'),
                __('Configured the :gateway payment gateway.', ['gateway' => $gateway->label]),
                ['driver' => $gateway->driver, 'fields' => $changed],
            );
        }

        return back()->with('success', __(':gateway updated.', ['gateway' => $gateway->label]));
    }

    /**
     * Reorder the checkout picker.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $this->authorizeOperator($request);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('payment_gateways', 'id')],
        ]);

        foreach ($validated['ids'] as $position => $id) {
            PaymentGatewayConfig::query()->whereKey($id)->update(['sort' => $position]);
        }

        $this->configs->flush();

        return back();
    }

    /**
     * An authenticated call to the processor, so a misconfiguration is found
     * here rather than by a customer at checkout.
     */
    public function test(Request $request, PaymentGatewayConfig $gateway): RedirectResponse
    {
        $this->authorizeOperator($request);

        try {
            $error = $this->gateways->driver($gateway->driver)->ping();
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $gateway->forceFill([
            'last_tested_at' => CarbonImmutable::now(),
            'last_test_error' => $error === null ? null : mb_substr($error, 0, 500),
        ])->save();

        $this->configs->flush();

        return back()->with(
            $error === null ? 'success' : 'error',
            $error === null
                ? __(':gateway responded normally.', ['gateway' => $gateway->label])
                : __(':gateway rejected the call: :error', ['gateway' => $gateway->label, 'error' => $error]),
        );
    }

    /**
     * A stored secret survives a save it was not part of.
     *
     * The form never receives the value, so a field coming back as the mask —
     * or empty — means "unchanged", never "clear it". Without this, saving the
     * currency list would wipe every credential on the gateway.
     *
     * @param  list<array{key: string, label: string, secret: bool, help?: string}>  $fields
     * @param  array<string, mixed>  $submitted
     * @return array<string, string>
     */
    protected function mergeCredentials(PaymentGatewayConfig $gateway, array $fields, array $submitted): array
    {
        $merged = $gateway->credentials ?? [];

        foreach ($fields as $field) {
            $key = $field['key'];

            if (! array_key_exists($key, $submitted)) {
                continue;
            }

            $value = $submitted[$key];

            if (! is_string($value) || $value === self::MASK) {
                continue;
            }

            // An explicitly emptied non-secret field is a real instruction to
            // clear it; a secret is only ever replaced, never blanked by accident.
            if ($value === '') {
                if (! $field['secret']) {
                    unset($merged[$key]);
                }

                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(PaymentGatewayConfig $config): array
    {
        $definition = $this->registry->get($config->driver);
        $fields = $definition['credentials'] ?? [];

        $credentials = [];

        foreach ($fields as $field) {
            $stored = $config->credential($field['key']);

            $credentials[$field['key']] = [
                'label' => $field['label'],
                'secret' => $field['secret'],
                'help' => $field['help'] ?? null,
                'is_set' => $stored !== null,
                // A secret is reported as set, never returned.
                'value' => $field['secret'] ? ($stored === null ? '' : self::MASK) : ($stored ?? ''),
            ];
        }

        return [
            'id' => $config->id,
            'driver' => $config->driver,
            'label' => $config->label,
            'description' => $definition['description'] ?? '',
            'is_enabled' => $config->is_enabled,
            'is_test_mode' => $config->is_test_mode,
            'is_ready' => $config->isReady($this->registry),
            'supports_refunds' => $definition['supports_refunds'] ?? false,
            'sort' => $config->sort,
            'currencies' => $config->currencies ?? [],
            'countries' => $config->countries ?? [],
            'credentials' => $credentials,
            'last_tested_at' => $config->last_tested_at?->toIso8601String(),
            'last_test_error' => $config->last_test_error,
            'recent' => $this->recentActivity($config->driver),
        ];
    }

    /**
     * What this processor has actually done lately — the thing that makes a
     * half-working integration visible.
     *
     * @return array{transactions: int, failures: int, events: int, unprocessed: int}
     */
    protected function recentActivity(string $driver): array
    {
        $since = CarbonImmutable::now()->subDays(30);

        $transactions = Transaction::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('gateway', $driver)
            ->where('created_at', '>=', $since);

        return [
            'transactions' => (clone $transactions)->count(),
            'failures' => (clone $transactions)->where('status', TransactionStatus::Failed->value)->count(),
            'events' => WebhookEvent::query()->where('gateway', $driver)->where('created_at', '>=', $since)->count(),
            'unprocessed' => WebhookEvent::query()->where('gateway', $driver)->whereNull('processed_at')->count(),
        ];
    }

    /**
     * @return list<string>
     */
    protected function currencyOptions(): array
    {
        $currencies = [];

        foreach ($this->registry->all() as $definition) {
            foreach ($definition['currencies'] as $currency) {
                $currencies[$currency] = true;
            }
        }

        $keys = array_keys($currencies);
        sort($keys);

        return $keys;
    }

    protected function authorizeOperator(Request $request): void
    {
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin && $admin->can('platform.gateways.manage'), 403);
    }
}
