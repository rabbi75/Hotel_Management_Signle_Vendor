<?php

declare(strict_types=1);

namespace App\Modules\Company\DTOs;

use App\Modules\User\Models\User;
use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class CompanyData extends Data
{
    public function __construct(
        public string $name,
        public ?int $ownerId = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $website = null,
        public ?string $taxId = null,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $postalCode = null,
        public ?string $countryCode = null,
        public string $timezone = 'UTC',
        public string $currency = 'USD',
        public string $locale = 'en',
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: (string) $request->string('name'),
            email: $request->string('email')->toString() ?: null,
            phone: $request->string('phone')->toString() ?: null,
            website: $request->string('website')->toString() ?: null,
            taxId: $request->string('tax_id')->toString() ?: null,
            addressLine1: $request->string('address_line_1')->toString() ?: null,
            addressLine2: $request->string('address_line_2')->toString() ?: null,
            city: $request->string('city')->toString() ?: null,
            state: $request->string('state')->toString() ?: null,
            postalCode: $request->string('postal_code')->toString() ?: null,
            countryCode: $request->string('country_code')->toString() ?: null,
            timezone: $request->string('timezone', (string) config('saas.defaults.timezone'))->toString(),
            currency: $request->string('currency', (string) config('saas.defaults.currency'))->toString(),
            locale: $request->string('locale', (string) config('saas.defaults.locale'))->toString(),
        );
    }

    /**
     * The minimal workspace created alongside a new account at sign-up.
     */
    public static function forRegistration(string $name, User $owner): self
    {
        return new self(
            name: $name,
            ownerId: $owner->id,
            email: $owner->email,
            timezone: $owner->timezone,
            currency: (string) config('saas.defaults.currency'),
            locale: $owner->locale,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return array_filter([
            'name' => $this->name,
            'owner_id' => $this->ownerId,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'tax_id' => $this->taxId,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
            'country_code' => $this->countryCode,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'locale' => $this->locale,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
