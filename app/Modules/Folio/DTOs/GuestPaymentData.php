<?php

declare(strict_types=1);

namespace App\Modules\Folio\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class GuestPaymentData extends Data
{
    public const FIELDS = ['amount', 'method', 'reference', 'notes', 'paid_at'];

    /** @param list<string> $provided */
    public function __construct(
        public int $amount,
        public string $method = 'cash',
        public ?string $reference = null,
        public ?string $notes = null,
        public ?string $paidAt = null,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(self::FIELDS, static fn (string $f): bool => $request->has($f)));

        return new self(
            amount: (int) $request->integer('amount'),
            method: (string) $request->string('method', 'cash'),
            reference: $request->input('reference') ?: null,
            notes: $request->input('notes') ?: null,
            paidAt: $request->input('paid_at') ?: null,
            provided: $provided,
        );
    }
}
