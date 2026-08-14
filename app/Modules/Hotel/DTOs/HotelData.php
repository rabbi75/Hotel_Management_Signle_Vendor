<?php

declare(strict_types=1);

namespace App\Modules\Hotel\DTOs;

use App\Modules\Hotel\Enums\HotelStatus;
use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class HotelData extends Data
{
    public const FIELDS = [
        'name', 'description', 'address', 'city', 'state', 'country', 'postal_code',
        'phone', 'email', 'website', 'check_in_time', 'check_out_time', 'currency',
        'timezone', 'tax_rate', 'tax_name', 'policies', 'contact_name', 'contact_phone',
        'contact_email', 'status', 'is_active',
    ];

    /**
     * @param  list<string>  $provided
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $country = null,
        public ?string $postalCode = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $website = null,
        public string $checkInTime = '14:00',
        public string $checkOutTime = '11:00',
        public string $currency = 'USD',
        public string $timezone = 'UTC',
        public float $taxRate = 0,
        public ?string $taxName = null,
        public ?string $policies = null,
        public ?string $contactName = null,
        public ?string $contactPhone = null,
        public ?string $contactEmail = null,
        public HotelStatus $status = HotelStatus::Active,
        public bool $isActive = true,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(
            self::FIELDS,
            static fn (string $field): bool => $request->has($field),
        ));

        $status = $request->input('status', HotelStatus::Active->value);

        return new self(
            name: (string) $request->string('name'),
            description: $request->input('description') ?: null,
            address: $request->input('address') ?: null,
            city: $request->input('city') ?: null,
            state: $request->input('state') ?: null,
            country: $request->input('country') ?: null,
            postalCode: $request->input('postal_code') ?: null,
            phone: $request->input('phone') ?: null,
            email: $request->input('email') ?: null,
            website: $request->input('website') ?: null,
            checkInTime: (string) ($request->input('check_in_time') ?: '14:00'),
            checkOutTime: (string) ($request->input('check_out_time') ?: '11:00'),
            currency: strtoupper((string) ($request->input('currency') ?: 'USD')),
            timezone: (string) ($request->input('timezone') ?: config('saas.defaults.timezone', 'UTC')),
            taxRate: (float) $request->input('tax_rate', 0),
            taxName: $request->input('tax_name') ?: null,
            policies: $request->input('policies') ?: null,
            contactName: $request->input('contact_name') ?: null,
            contactPhone: $request->input('contact_phone') ?: null,
            contactEmail: $request->input('contact_email') ?: null,
            status: HotelStatus::tryFrom((string) $status) ?? HotelStatus::Active,
            isActive: $request->boolean('is_active', true),
            provided: $provided,
        );
    }

    public function wasProvided(string $field): bool
    {
        return in_array($field, $this->provided, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postalCode,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'check_in_time' => $this->checkInTime,
            'check_out_time' => $this->checkOutTime,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'tax_rate' => $this->taxRate,
            'tax_name' => $this->taxName,
            'policies' => $this->policies,
            'contact_name' => $this->contactName,
            'contact_phone' => $this->contactPhone,
            'contact_email' => $this->contactEmail,
            'status' => $this->status,
            'is_active' => $this->isActive,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toUpdateAttributes(): array
    {
        return array_intersect_key($this->toAttributes(), array_flip($this->provided));
    }
}