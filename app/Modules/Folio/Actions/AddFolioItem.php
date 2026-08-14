<?php

declare(strict_types=1);

namespace App\Modules\Folio\Actions;

use App\Modules\Folio\DTOs\FolioItemData;
use App\Modules\Folio\Enums\FolioItemType;
use App\Modules\Folio\Models\FolioItem;
use App\Modules\Folio\Models\GuestFolio;
use App\Modules\Folio\Models\HotelService;
use App\Modules\Folio\Services\FolioCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddFolioItem
{
    public function __construct(protected FolioCalculator $calculator) {}

    public function handle(GuestFolio $folio, FolioItemData $data): FolioItem
    {
        if (! $folio->isOpen()) {
            throw ValidationException::withMessages([
                'folio' => __('Charges cannot be added to a closed folio.'),
            ]);
        }

        return DB::transaction(function () use ($folio, $data): FolioItem {
            $service = null;
            $type = FolioItemType::Service;
            $description = $data->description;
            $unitPrice = $data->unitPrice;
            $quantity = $data->quantity;
            $taxAmount = 0;

            if ($data->hotelServiceId !== null) {
                $service = HotelService::query()->findOrFail($data->hotelServiceId);
                $description = $description ?: $service->name;
                $unitPrice = $unitPrice > 0 ? $unitPrice : $service->price;
                $taxAmount = $service->tax_rate > 0
                    ? (int) intdiv($unitPrice * $quantity * $service->tax_rate, 100)
                    : 0;
            }

            if ($description === null || trim($description) === '') {
                throw ValidationException::withMessages([
                    'description' => __('A description is required.'),
                ]);
            }

            $amount = $data->amount ?? ($unitPrice * $quantity);

            $item = $folio->items()->create([
                'hotel_service_id' => $service?->id,
                'type' => $type,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'tax_amount' => $taxAmount,
                'posted_at' => CarbonImmutable::now(),
                'posted_by' => auth()->id(),
            ]);

            $this->calculator->recalculate($folio);

            return $item;
        });
    }
}
