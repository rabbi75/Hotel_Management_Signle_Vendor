<?php

declare(strict_types=1);

use App\Modules\Hotel\Enums\HotelStatus;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores a room photo from the create form', function (): void {
    Storage::fake('public');

    $company = workspace();
    $hotel = new Hotel([
        'name' => 'Seaside Resort',
        'currency' => 'USD',
        'status' => HotelStatus::Active,
        'is_active' => true,
        'check_in_time' => '14:00',
        'check_out_time' => '11:00',
    ]);
    $hotel->company_id = $company->id;
    $hotel->save();

    $user = memberWith(['rooms.view', 'rooms.create'], $company);

    actingAsMember($user, $company)
        ->post(route('rooms.store'), [
            'hotel_id' => $hotel->id,
            'number' => '401',
            'status' => 'available',
            'is_active' => true,
            'image' => UploadedFile::fake()->image('suite.jpg', 800, 600),
        ])
        ->assertRedirect(route('rooms.index'));

    $room = Room::query()->where('number', '401')->first();

    expect($room)->not->toBeNull()
        ->and($room->getFirstMedia('photo'))->not->toBeNull()
        ->and($room->imageUrl())->not->toBeNull();
});
