<?php

declare(strict_types=1);

namespace App\Modules\Guest\DTOs;

use App\Modules\Guest\Enums\GuestGender;
use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class GuestData extends Data
{
    public const FIELDS = [
        'hotel_id', 'first_name', 'last_name', 'gender', 'date_of_birth',
        'phone', 'email', 'address', 'city', 'country', 'nationality',
        'id_type', 'id_number', 'emergency_contact_name', 'emergency_contact_phone',
        'notes', 'is_vip', 'is_blacklisted',
    ];

    /**
     * @param  list<string>  $provided
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?int $hotelId = null,
        public ?GuestGender $gender = null,
        public ?string $dateOfBirth = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $country = null,
        public ?string $nationality = null,
        public ?string $idType = null,
        public ?string $idNumber = null,
        public ?string $emergencyContactName = null,
        public ?string $emergencyContactPhone = null,
        public ?string $notes = null,
        public bool $isVip = false,
        public bool $isBlacklisted = false,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(self::FIELDS, static fn (string $f): bool => $request->has($f)));
        $hotelId = $request->input('hotel_id');
        $gender = $request->input('gender');

        return new self(
            firstName: (string) $request->string('first_name'),
            lastName: (string) $request->string('last_name'),
            hotelId: is_numeric($hotelId) ? (int) $hotelId : null,
            gender: is_string($gender) && $gender !== '' ? GuestGender::tryFrom($gender) : null,
            dateOfBirth: $request->input('date_of_birth') ?: null,
            phone: $request->input('phone') ?: null,
            email: $request->input('email') ?: null,
            address: $request->input('address') ?: null,
            city: $request->input('city') ?: null,
            country: $request->input('country') ?: null,
            nationality: $request->input('nationality') ?: null,
            idType: $request->input('id_type') ?: null,
            idNumber: $request->input('id_number') ?: null,
            emergencyContactName: $request->input('emergency_contact_name') ?: null,
            emergencyContactPhone: $request->input('emergency_contact_phone') ?: null,
            notes: $request->input('notes') ?: null,
            isVip: $request->boolean('is_vip'),
            isBlacklisted: $request->boolean('is_blacklisted'),
            provided: $provided,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'hotel_id' => $this->hotelId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'gender' => $this->gender,
            'date_of_birth' => $this->dateOfBirth,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'nationality' => $this->nationality,
            'id_type' => $this->idType,
            'id_number' => $this->idNumber,
            'emergency_contact_name' => $this->emergencyContactName,
            'emergency_contact_phone' => $this->emergencyContactPhone,
            'notes' => $this->notes,
            'is_vip' => $this->isVip,
            'is_blacklisted' => $this->isBlacklisted,
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
