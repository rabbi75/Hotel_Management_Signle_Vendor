<?php

declare(strict_types=1);

namespace App\Modules\Folio\Actions;

use App\Modules\Folio\Models\FolioItem;
use App\Modules\Folio\Models\GuestFolio;
use App\Modules\Folio\Services\FolioCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveFolioItem
{
    public function __construct(protected FolioCalculator $calculator) {}

    public function handle(GuestFolio $folio, FolioItem $item): void
    {
        if (! $folio->isOpen()) {
            throw ValidationException::withMessages([
                'folio' => __('Items cannot be removed from a closed folio.'),
            ]);
        }

        if ($item->guest_folio_id !== $folio->id) {
            throw ValidationException::withMessages([
                'item' => __('This charge does not belong to the folio.'),
            ]);
        }

        DB::transaction(function () use ($folio, $item): void {
            $item->delete();
            $this->calculator->recalculate($folio);
        });
    }
}
