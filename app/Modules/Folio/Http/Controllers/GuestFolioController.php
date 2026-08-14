<?php

declare(strict_types=1);

namespace App\Modules\Folio\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Folio\Actions\AddFolioItem;
use App\Modules\Folio\Actions\CloseGuestFolio;
use App\Modules\Folio\Actions\OpenGuestFolio;
use App\Modules\Folio\Actions\RecordGuestPayment;
use App\Modules\Folio\Actions\RemoveFolioItem;
use App\Modules\Folio\DTOs\FolioItemData;
use App\Modules\Folio\DTOs\GuestPaymentData;
use App\Modules\Folio\Enums\FolioStatus;
use App\Modules\Folio\Http\Requests\StoreFolioItemRequest;
use App\Modules\Folio\Http\Requests\StoreGuestPaymentRequest;
use App\Modules\Folio\Http\Resources\GuestFolioResource;
use App\Modules\Folio\Models\FolioItem;
use App\Modules\Folio\Models\GuestFolio;
use App\Modules\Folio\Models\HotelService;
use App\Modules\Hotel\Http\Controllers\Concerns\ProvidesHotelOptions;
use App\Modules\Reservation\Models\Reservation;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GuestFolioController extends Controller
{
    use ProvidesHotelOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', GuestFolio::class);

        $query = GuestFolio::query()->with(['guest', 'hotel', 'reservation']);

        if (current_hotel_id() !== null) {
            $query->where('hotel_id', current_hotel_id());
        }

        $table = TableBuilder::for($query, $request, 'folios')
            ->columns([
                Column::make('number', __('Folio'))->sortable()->searchable()->locked(),
                Column::make('guest', __('Guest'))->sortable('guest_id'),
                Column::make('hotel', __('Hotel'))->sortable('hotel_id'),
                Column::make('status', __('Status'))->sortable(),
                Column::make('total', __('Total'))->sortable()->align('right'),
                Column::make('balance', __('Balance'))->sortable()->align('right'),
                Column::make('opened_at', __('Opened'))->sortable(),
            ])
            ->filters([
                Filter::make('hotel_id', __('Hotel'))->options($this->hotelOptions()),
                Filter::make('status', __('Status'))->fromEnum(FolioStatus::class)->multiple(),
            ])
            ->defaultSort('opened_at', 'desc')
            ->transform(fn (GuestFolio $folio): array => (new GuestFolioResource($folio))->resolve($request));

        return Inertia::render('folios/index', [
            'table' => $table->toArray(),
        ]);
    }

    public function show(Request $request, GuestFolio $folio): Response
    {
        Gate::authorize('view', $folio);

        $folio->load(['guest', 'hotel', 'reservation', 'items', 'payments', 'invoice']);

        return Inertia::render('folios/show', [
            'folio' => (new GuestFolioResource($folio))->resolve($request),
            'services' => $this->serviceOptions($folio->hotel_id),
            'can' => [
                'manage' => Gate::allows('manage', $folio),
                'add_charge' => Gate::allows('addCharge', $folio),
                'record_payment' => Gate::allows('recordPayment', $folio),
                'close' => Gate::allows('close', $folio),
            ],
        ]);
    }

    public function openFromReservation(Reservation $reservation, OpenGuestFolio $openFolio): RedirectResponse
    {
        Gate::authorize('viewAny', GuestFolio::class);
        Gate::authorize('view', $reservation);

        $folio = $openFolio->handle($reservation);

        return redirect()->route('folios.show', $folio)->with('success', __('Folio opened.'));
    }

    public function storeItem(StoreFolioItemRequest $request, GuestFolio $folio, AddFolioItem $addItem): RedirectResponse
    {
        $addItem->handle($folio, FolioItemData::fromRequest($request));

        return back()->with('success', __('Charge posted to folio.'));
    }

    public function destroyItem(GuestFolio $folio, FolioItem $item, RemoveFolioItem $removeItem): RedirectResponse
    {
        Gate::authorize('manage', $folio);
        $removeItem->handle($folio, $item);

        return back()->with('success', __('Charge removed.'));
    }

    public function storePayment(StoreGuestPaymentRequest $request, GuestFolio $folio, RecordGuestPayment $recordPayment): RedirectResponse
    {
        $recordPayment->handle($folio, GuestPaymentData::fromRequest($request));

        return back()->with('success', __('Payment recorded.'));
    }

    public function close(Request $request, GuestFolio $folio, CloseGuestFolio $closeFolio): RedirectResponse
    {
        Gate::authorize('close', $folio);

        $closeFolio->handle($folio, [
            'allow_balance' => $request->boolean('allow_balance'),
            'notes' => $request->input('notes'),
        ]);

        return back()->with('success', __('Folio closed.'));
    }

    /** @return array<string, string> */
    protected function serviceOptions(?int $hotelId): array
    {
        $query = HotelService::query()->where('is_active', true)->orderBy('name');

        if ($hotelId !== null) {
            $query->where(function ($q) use ($hotelId): void {
                $q->whereNull('hotel_id')->orWhere('hotel_id', $hotelId);
            });
        }

        return $query->pluck('name', 'id')
            ->mapWithKeys(fn ($n, $i): array => [(string) $i => (string) $n])
            ->all();
    }
}
