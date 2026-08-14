<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

/**
 * The catalogue of payment processors the kit knows how to talk to.
 *
 * One declaration per driver, describing what the console needs to render its
 * form and what the checkout flow needs to decide whether a gateway may be
 * offered. Keeping it here rather than in each driver class means the settings
 * screen can list a processor — and an operator can enter its credentials —
 * without the driver ever being constructed.
 *
 * A `credentials` entry is a field the operator supplies. `secret: true` marks
 * one that is masked on read and never sent back to the browser, exactly as
 * SettingsSchema does for the api_keys group.
 *
 * @phpstan-type CredentialField array{key: string, label: string, secret: bool, help?: string}
 * @phpstan-type Definition array{
 *     driver: string,
 *     label: string,
 *     description: string,
 *     credentials: list<CredentialField>,
 *     currencies: list<string>,
 *     supports_refunds: bool,
 *     hosted: bool,
 * }
 */
class GatewayRegistry
{
    /**
     * Every driver, in the order a fresh installation lists them.
     *
     * `currencies` is what the processor actually settles in — an empty list
     * means "anything", which is only true of the offline driver. The console
     * seeds these as the default rule and an operator may narrow them further.
     *
     * @return array<string, Definition>
     */
    public function all(): array
    {
        return [
            'manual' => $this->define(
                'manual',
                'Manual / offline',
                'Records the subscription without contacting a processor. Payment is arranged out of band.',
                credentials: [],
                currencies: [],
                supportsRefunds: true,
                hosted: false,
            ),

            'stripe' => $this->define(
                'stripe',
                'Stripe',
                'Cards, wallets and local methods through Stripe Checkout.',
                credentials: [
                    self::field('secret_key', 'Secret key', secret: true, help: 'sk_live_… or sk_test_…'),
                    self::field('publishable_key', 'Publishable key', secret: false),
                    self::field('webhook_secret', 'Webhook signing secret', secret: true, help: 'whsec_…'),
                ],
                currencies: ['USD', 'EUR', 'GBP', 'AUD', 'CAD', 'INR', 'JPY'],
                supportsRefunds: true,
            ),

            'paypal' => $this->define(
                'paypal',
                'PayPal',
                'PayPal balance and cards, via Orders v2.',
                credentials: [
                    self::field('client_id', 'Client ID', secret: false),
                    self::field('client_secret', 'Client secret', secret: true),
                    self::field('webhook_id', 'Webhook ID', secret: false, help: 'Needed to verify inbound events.'),
                ],
                currencies: ['USD', 'EUR', 'GBP', 'AUD', 'CAD', 'JPY'],
                supportsRefunds: true,
            ),

            'paddle' => $this->define(
                'paddle',
                'Paddle',
                'Merchant of record — Paddle handles sales tax and invoicing.',
                credentials: [
                    self::field('api_key', 'API key', secret: true),
                    self::field('client_token', 'Client-side token', secret: false),
                    self::field('webhook_secret', 'Webhook secret', secret: true),
                ],
                currencies: ['USD', 'EUR', 'GBP', 'AUD', 'CAD'],
                supportsRefunds: true,
            ),

            'lemonsqueezy' => $this->define(
                'lemonsqueezy',
                'Lemon Squeezy',
                'Merchant of record aimed at software sales.',
                credentials: [
                    self::field('api_key', 'API key', secret: true),
                    self::field('store_id', 'Store ID', secret: false),
                    self::field('webhook_secret', 'Webhook signing secret', secret: true),
                ],
                currencies: ['USD', 'EUR', 'GBP'],
                supportsRefunds: true,
            ),

            'braintree' => $this->define(
                'braintree',
                'Braintree',
                'PayPal-owned card processing with a hosted drop-in.',
                credentials: [
                    self::field('merchant_id', 'Merchant ID', secret: false),
                    self::field('public_key', 'Public key', secret: false),
                    self::field('private_key', 'Private key', secret: true),
                ],
                currencies: ['USD', 'EUR', 'GBP', 'AUD', 'CAD'],
                supportsRefunds: true,
            ),

            'square' => $this->define(
                'square',
                'Square',
                'Card payments through a Square-hosted checkout link.',
                credentials: [
                    self::field('access_token', 'Access token', secret: true),
                    self::field('location_id', 'Location ID', secret: false),
                    self::field('webhook_signature_key', 'Webhook signature key', secret: true),
                ],
                currencies: ['USD', 'CAD', 'GBP', 'AUD', 'JPY'],
                supportsRefunds: true,
            ),

            'mollie' => $this->define(
                'mollie',
                'Mollie',
                'European methods — iDEAL, Bancontact, SEPA and cards.',
                credentials: [
                    self::field('api_key', 'API key', secret: true, help: 'live_… or test_…'),
                ],
                currencies: ['EUR', 'GBP', 'CHF', 'PLN', 'DKK', 'SEK', 'NOK'],
                supportsRefunds: true,
            ),

            'razorpay' => $this->define(
                'razorpay',
                'Razorpay',
                'Cards, UPI and netbanking for India.',
                credentials: [
                    self::field('key_id', 'Key ID', secret: false),
                    self::field('key_secret', 'Key secret', secret: true),
                    self::field('webhook_secret', 'Webhook secret', secret: true),
                ],
                currencies: ['INR', 'USD'],
                supportsRefunds: true,
            ),

            'paystack' => $this->define(
                'paystack',
                'Paystack',
                'Cards, bank transfer and mobile money across Africa.',
                credentials: [
                    self::field('secret_key', 'Secret key', secret: true, help: 'sk_live_… or sk_test_…'),
                    self::field('public_key', 'Public key', secret: false),
                ],
                currencies: ['NGN', 'GHS', 'ZAR', 'KES', 'USD'],
                supportsRefunds: true,
            ),

            'flutterwave' => $this->define(
                'flutterwave',
                'Flutterwave',
                'Pan-African cards, mobile money and bank transfer.',
                credentials: [
                    self::field('secret_key', 'Secret key', secret: true),
                    self::field('public_key', 'Public key', secret: false),
                    self::field('encryption_key', 'Encryption key', secret: true),
                    self::field('webhook_hash', 'Webhook hash', secret: true),
                ],
                currencies: ['NGN', 'GHS', 'KES', 'ZAR', 'UGX', 'TZS', 'USD', 'GBP', 'EUR'],
                supportsRefunds: true,
            ),

            /*
            | Bangladesh.
            |
            | SSLCommerz leads because one integration reaches every wallet and
            | card scheme at once; the two direct-wallet drivers below are for
            | operators with the volume to want the lower per-transaction cost.
            | Both of those, and aamarPay, publish no merchant refund API — a
            | refund is raised in the processor's own portal, which is why they
            | declare `supports_refunds: false` rather than pretending.
            */

            'sslcommerz' => $this->define(
                'sslcommerz',
                'SSLCommerz',
                'The Bangladeshi aggregator — bKash, Nagad, Rocket, Upay, cards and net banking through one integration.',
                credentials: [
                    self::field('store_id', 'Store ID', secret: false),
                    self::field('store_password', 'Store password', secret: true, help: 'The API password, not the merchant panel login.'),
                ],
                currencies: ['BDT', 'USD', 'EUR', 'GBP'],
                supportsRefunds: true,
            ),

            'bkash' => $this->define(
                'bkash',
                'bKash',
                'The bKash wallet directly, through Tokenized Checkout.',
                credentials: [
                    self::field('app_key', 'App key', secret: false),
                    self::field('app_secret', 'App secret', secret: true),
                    self::field('username', 'Merchant username', secret: false),
                    self::field('password', 'Merchant password', secret: true),
                ],
                currencies: ['BDT'],
                supportsRefunds: true,
            ),

            'nagad' => $this->define(
                'nagad',
                'Nagad',
                'The Nagad wallet directly. Requires the RSA key pair Nagad issues with the merchant account.',
                credentials: [
                    self::field('merchant_id', 'Merchant ID', secret: false),
                    self::field('merchant_number', 'Merchant account number', secret: false),
                    self::field('public_key', 'Nagad public key', secret: true, help: 'Base64 or PEM. Used to encrypt requests to Nagad.'),
                    self::field('private_key', 'Merchant private key', secret: true, help: 'Base64 or PEM. Used to sign requests and read responses.'),
                ],
                currencies: ['BDT'],
                supportsRefunds: false,
            ),

            'aamarpay' => $this->define(
                'aamarpay',
                'aamarPay',
                'Bangladeshi aggregator — mobile wallets, cards and net banking.',
                credentials: [
                    self::field('store_id', 'Store ID', secret: false),
                    self::field('signature_key', 'Signature key', secret: true),
                ],
                currencies: ['BDT', 'USD'],
                supportsRefunds: false,
            ),
        ];
    }

    /**
     * @return list<string>
     */
    public function drivers(): array
    {
        return array_keys($this->all());
    }

    public function has(string $driver): bool
    {
        return array_key_exists($driver, $this->all());
    }

    /**
     * @return Definition|null
     */
    public function get(string $driver): ?array
    {
        return $this->all()[$driver] ?? null;
    }

    /**
     * Credential keys that must never be echoed back to the browser.
     *
     * @return list<string>
     */
    public function secretKeys(string $driver): array
    {
        $definition = $this->get($driver);

        if ($definition === null) {
            return [];
        }

        return array_values(array_map(
            static fn (array $field): string => $field['key'],
            array_filter($definition['credentials'], static fn (array $field): bool => $field['secret']),
        ));
    }

    /**
     * @param  list<CredentialField>  $credentials
     * @param  list<string>  $currencies
     * @return Definition
     */
    private function define(
        string $driver,
        string $label,
        string $description,
        array $credentials,
        array $currencies,
        bool $supportsRefunds,
        bool $hosted = true,
    ): array {
        return [
            'driver' => $driver,
            'label' => $label,
            'description' => $description,
            'credentials' => $credentials,
            'currencies' => $currencies,
            'supports_refunds' => $supportsRefunds,
            'hosted' => $hosted,
        ];
    }

    /**
     * @return CredentialField
     */
    private static function field(string $key, string $label, bool $secret, ?string $help = null): array
    {
        $field = ['key' => $key, 'label' => $label, 'secret' => $secret];

        return $help === null ? $field : [...$field, 'help' => $help];
    }
}
