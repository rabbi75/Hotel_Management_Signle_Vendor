<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Company\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $total = 1900;

        return [
            'company_id' => Company::factory(),
            'subscription_id' => null,
            'number' => 'INV-'.Str::upper(Str::random(10)),
            'status' => InvoiceStatus::Open,
            'subtotal' => $total,
            'tax' => 0,
            'discount' => 0,
            'total' => $total,
            'currency' => 'USD',
            'issued_at' => CarbonImmutable::now(),
            'due_at' => CarbonImmutable::now()->addDays(7),
            'paid_at' => null,
            'gateway' => 'manual',
            'gateway_id' => null,
            'notes' => null,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => ['company_id' => $company->id]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => CarbonImmutable::now(),
        ]);
    }
}
