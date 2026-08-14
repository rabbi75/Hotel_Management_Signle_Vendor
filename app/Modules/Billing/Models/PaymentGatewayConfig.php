<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Services\GatewayRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One configured payment processor.
 *
 * The credentials blob is encrypted at rest by the cast, so a database dump
 * does not hand over the installation's processor keys.
 *
 * @property int $id
 * @property string $driver
 * @property string $label
 * @property bool $is_enabled
 * @property bool $is_test_mode
 * @property int $sort
 * @property array<string, string>|null $credentials
 * @property list<string>|null $currencies
 * @property list<string>|null $countries
 * @property CarbonImmutable|null $last_tested_at
 * @property string|null $last_test_error
 */
class PaymentGatewayConfig extends Model
{
    protected $table = 'payment_gateways';

    protected $fillable = [
        'driver',
        'label',
        'is_enabled',
        'is_test_mode',
        'sort',
        'credentials',
        'currencies',
        'countries',
        'last_tested_at',
        'last_test_error',
    ];

    /**
     * Hidden from every serialisation by default. A resource that needs to say
     * whether a credential is *set* reports a boolean; the value itself never
     * leaves the server.
     *
     * @var list<string>
     */
    protected $hidden = ['credentials'];

    /**
     * @param  Builder<self>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    public function credential(string $key): ?string
    {
        $value = $this->credentials[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Whether this gateway may take a payment in the given currency.
     *
     * An empty list means the operator has set no restriction, which is not the
     * same as the processor supporting everything — {@see GatewayRegistry}
     * seeds the realistic default and the operator may narrow it.
     */
    public function supportsCurrency(string $currency): bool
    {
        $currencies = $this->currencies ?? [];

        return $currencies === [] || in_array(strtoupper($currency), array_map(strtoupper(...), $currencies), true);
    }

    public function supportsCountry(?string $country): bool
    {
        $countries = $this->countries ?? [];

        if ($countries === [] || $country === null) {
            return true;
        }

        return in_array(strtoupper($country), array_map(strtoupper(...), $countries), true);
    }

    /**
     * Configured enough to be offered: every non-secret and secret field the
     * registry declares has a value. A gateway enabled but half-configured
     * would fail at the worst possible moment — mid-checkout.
     */
    public function isReady(GatewayRegistry $registry): bool
    {
        $definition = $registry->get($this->driver);

        if ($definition === null) {
            return false;
        }

        foreach ($definition['credentials'] as $field) {
            if ($this->credential($field['key']) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_test_mode' => 'boolean',
            'sort' => 'integer',
            'credentials' => 'encrypted:array',
            'currencies' => 'array',
            'countries' => 'array',
            'last_tested_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
