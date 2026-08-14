<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\PaymentGatewayConfig;
use Illuminate\Support\Collection;

/**
 * Reads the configured processors, once per request.
 *
 * Checkout touches the same rows several times — resolving the driver, deciding
 * which gateways to offer, reading a credential — and the credentials column is
 * encrypted, so decryption is worth doing once rather than per lookup.
 */
class GatewayConfigRepository
{
    /** @var Collection<string, PaymentGatewayConfig>|null */
    protected ?Collection $cache = null;

    /**
     * @return Collection<string, PaymentGatewayConfig>
     */
    public function all(): Collection
    {
        return $this->cache ??= PaymentGatewayConfig::query()
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->keyBy('driver');
    }

    public function find(string $driver): ?PaymentGatewayConfig
    {
        return $this->all()->get($driver);
    }

    /**
     * Enabled, fully configured processors, in the operator's chosen order.
     *
     * A gateway missing a credential is dropped rather than offered: failing at
     * the point of payment is the worst moment to discover a half-finished
     * configuration.
     *
     * @return Collection<string, PaymentGatewayConfig>
     */
    public function enabled(GatewayRegistry $registry): Collection
    {
        return $this->all()
            ->filter(fn (PaymentGatewayConfig $config): bool => $config->is_enabled && $config->isReady($registry));
    }

    /**
     * Drop the memoised rows. Called after the console writes a change so the
     * same request sees it.
     */
    public function flush(): void
    {
        $this->cache = null;
    }
}
