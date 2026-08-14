<?php

declare(strict_types=1);

namespace App\Modules\HotelReports\DTOs;

use App\Support\DTOs\Data;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

readonly class ReportPeriodData extends Data
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?int $hotelId = null,
    ) {}

    public static function fromRequest(Request $request, int $defaultDays = 14): self
    {
        $to = $request->filled('to')
            ? CarbonImmutable::parse((string) $request->string('to'))->startOfDay()
            : CarbonImmutable::today();

        $from = $request->filled('from')
            ? CarbonImmutable::parse((string) $request->string('from'))->startOfDay()
            : $to->subDays(max(1, $defaultDays) - 1);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $hotelId = $request->input('hotel_id');

        return new self(
            from: $from,
            to: $to,
            hotelId: is_numeric($hotelId) ? (int) $hotelId : current_hotel_id(),
        );
    }

    /** @return array<string, string|null> */
    public function toQuery(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'hotel_id' => $this->hotelId !== null ? (string) $this->hotelId : null,
        ];
    }
}
