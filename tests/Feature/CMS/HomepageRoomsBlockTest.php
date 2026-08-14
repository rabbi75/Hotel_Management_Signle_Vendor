<?php

declare(strict_types=1);

use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Services\BlockRegistry;
use App\Modules\CMS\Services\PageService;
use App\Modules\Hotel\Enums\HotelStatus;
use App\Modules\Hotel\Enums\RoomStatus;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\OnlineBooking\Models\BookingSetting;

use function Pest\Laravel\get;

it('renders live rooms on the published homepage', function (): void {
    $company = workspace();

    $hotel = new Hotel([
        'name' => 'Seaside Resort',
        'currency' => 'USD',
        'status' => HotelStatus::Active,
        'is_active' => true,
        'city' => 'Dhaka',
        'country' => 'Bangladesh',
        'check_in_time' => '14:00',
        'check_out_time' => '11:00',
    ]);
    $hotel->company_id = $company->id;
    $hotel->save();

    $setting = new BookingSetting([
        'hotel_id' => $hotel->id,
        'is_enabled' => true,
        'public_slug' => 'seaside',
        'min_advance_days' => 0,
        'max_advance_days' => 365,
    ]);
    $setting->company_id = $company->id;
    $setting->save();

    $roomType = new RoomType([
        'hotel_id' => $hotel->id,
        'name' => 'Standard Double',
        'code' => 'STD',
        'base_price' => 12000,
        'max_adults' => 2,
        'max_occupancy' => 2,
        'is_active' => true,
    ]);
    $roomType->company_id = $company->id;
    $roomType->save();

    $room = new Room([
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => '101',
        'status' => RoomStatus::Available,
        'is_active' => true,
    ]);
    $room->company_id = $company->id;
    $room->save();

    $page = Page::factory()->forCompany($company)->published()->create([
        'is_homepage' => true,
        'title' => 'Home',
        'slug' => 'home',
    ]);

    $schema = app(BlockRegistry::class)->get('rooms');
    expect($schema)->not->toBeNull();
    app(PageService::class)->insertBlock($page, $schema, null, [
        'heading' => 'Rooms for tonight',
        'section_id' => 'rooms',
    ]);

    $response = get('/', inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'cms/public-page');

    $roomsBlock = collect($response->json('props.page.blocks'))->firstWhere('type', 'rooms');

    expect($roomsBlock)->toBeArray()
        ->and($roomsBlock['data']['heading'])->toBe('Rooms for tonight')
        ->and($roomsBlock['data']['rooms'][0]['name'])->toBe('Standard Double')
        ->and($roomsBlock['data']['rooms'][0]['available_rooms'])->toBe(1)
        ->and($roomsBlock['data']['rooms'][0]['image'])->toBeString()->not->toBe('')
        ->and($roomsBlock['data']['slug'])->toBe('seaside');
});
