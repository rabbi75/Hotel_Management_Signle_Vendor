<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Department;
use App\Modules\Company\Models\Team;
use App\Modules\Folio\Actions\AddFolioItem;
use App\Modules\Folio\Actions\GenerateGuestInvoice;
use App\Modules\Folio\DTOs\FolioItemData;
use App\Modules\Folio\Enums\FolioItemType;
use App\Modules\Folio\Enums\FolioStatus;
use App\Modules\Folio\Enums\GuestPaymentMethod;
use App\Modules\Folio\Enums\GuestPaymentStatus;
use App\Modules\Folio\Enums\HotelServiceCategory;
use App\Modules\Folio\Models\GuestFolio;
use App\Modules\Folio\Models\GuestPayment;
use App\Modules\Folio\Models\HotelService;
use App\Modules\Folio\Services\FolioCalculator;
use App\Modules\Guest\Enums\GuestGender;
use App\Modules\Guest\Models\Guest;
use App\Modules\Hotel\Enums\BedStatus;
use App\Modules\Hotel\Enums\HotelStatus;
use App\Modules\Hotel\Enums\RoomStatus;
use App\Modules\Hotel\Models\Bed;
use App\Modules\Hotel\Models\Building;
use App\Modules\Hotel\Models\Facility;
use App\Modules\Hotel\Models\Floor;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\HotelPos\Enums\PosOrderStatus;
use App\Modules\HotelPos\Models\PosOrder;
use App\Modules\HotelPos\Models\Restaurant;
use App\Modules\Housekeeping\Actions\CreateHousekeepingTask;
use App\Modules\Housekeeping\DTOs\HousekeepingTaskData;
use App\Modules\Housekeeping\Enums\HousekeepingPriority;
use App\Modules\Housekeeping\Enums\HousekeepingTaskStatus;
use App\Modules\Housekeeping\Enums\HousekeepingTaskType;
use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Maintenance\Actions\CreateMaintenanceRequest;
use App\Modules\Maintenance\DTOs\MaintenanceRequestData;
use App\Modules\Maintenance\Enums\MaintenanceCategory;
use App\Modules\Maintenance\Enums\MaintenancePriority;
use App\Modules\Maintenance\Enums\MaintenanceRequestStatus;
use App\Modules\Maintenance\Models\MaintenanceRequest;
use App\Modules\OnlineBooking\Actions\SeedBookingPaymentMethods;
use App\Modules\OnlineBooking\Models\BookingSetting;
use App\Modules\Reservation\Actions\CheckInReservation;
use App\Modules\Reservation\Actions\CreateReservation;
use App\Modules\Reservation\DTOs\ReservationData;
use App\Modules\Reservation\Enums\BookingSource;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\User\Enums\UserStatus;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Actions\CreateDefaultWorkspace;
use App\Modules\Workspace\Enums\WorkspaceMemberRole;
use App\Modules\Workspace\Enums\WorkspaceMemberStatus;
use App\Modules\Workspace\Models\Workspace;
use App\Support\Enums\Theme;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Replaces SaaS kit dummy rows with a single working hotel property.
 *
 * Safe to re-run: operational tables are emptied first, then seeded again.
 * The super-admin account and the first company/workspace are kept.
 */
class HotelManagementSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->orderBy('id')->first();
        $adminEmail = (string) config('saas.seed.admin_email');
        $admin = User::query()->where('email', $adminEmail)->first();

        if (! $company instanceof Company || ! $admin instanceof User) {
            $this->command?->error('Seed the admin user and company before hotel data.');

            return;
        }

        $this->purgeDummyData($company, $adminEmail);

        $workspace = app(CreateDefaultWorkspace::class)->handle($company, $admin);

        $company->forceFill([
            'name' => 'Grand Palms Hospitality',
            'email' => 'reservations@grandpalms.example',
            'phone' => '+880 2 5500 1200',
            'timezone' => 'Asia/Dhaka',
            'currency' => (string) config('saas.defaults.currency', 'USD'),
            'locale' => 'en',
        ])->save();

        $workspace->forceFill([
            'name' => 'Grand Palms Hotel',
            'is_default' => true,
            'timezone' => 'Asia/Dhaka',
            'currency' => $company->currency,
        ])->save();

        app(CurrentCompany::class)->scopeTo($company, function () use ($company, $workspace, $admin): void {
            app(CurrentWorkspace::class)->scopeTo($workspace, function () use ($company, $workspace, $admin): void {
                $staff = $this->seedStaff($company, $workspace, $admin);
                $this->seedDepartments($company, $staff);
                $hotel = $this->seedProperty($company);
                $this->seedOperations($hotel, $staff, $admin);
            });
        });

        $this->command?->info('Seeded Grand Palms Hotel operational data.');
    }

    protected function purgeDummyData(Company $keep, string $adminEmail): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'pos_orders',
            'restaurants',
            'guest_invoice_lines',
            'guest_invoices',
            'guest_payments',
            'folio_items',
            'guest_folios',
            'housekeeping_tasks',
            'maintenance_requests',
            'reservations',
            'hotel_services',
            'booking_settings',
            'facilityables',
            'beds',
            'rooms',
            'room_types',
            'hotel_floors',
            'hotel_buildings',
            'hotels',
            'guests',
            'coupon_redemptions',
            'coupons',
            'invoice_lines',
            'invoices',
            'usage_records',
            'transactions',
            'payment_methods',
            'webhook_events',
            'subscriptions',
            'plans',
            'company_invitations',
            'company_support_notes',
            'support_ticket_messages',
            'support_tickets',
            'blog_comments',
            'blog_post_tag',
            'blog_posts',
            'blog_tags',
            'blog_categories',
            'platform_announcements',
            'admin_login_histories',
            'admins',
            'activity_log',
            'media',
            'login_histories',
            'notifications',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        if (Schema::hasTable('company_user')) {
            DB::table('company_user')->where('company_id', '!=', $keep->id)->delete();
            DB::table('company_user')->update(['department_id' => null]);
        }

        if (Schema::hasTable('team_user')) {
            DB::table('team_user')->truncate();
        }

        if (Schema::hasTable('teams')) {
            DB::table('teams')->update(['lead_id' => null, 'department_id' => null]);
            DB::table('teams')->truncate();
        }

        if (Schema::hasTable('departments')) {
            DB::table('departments')->update(['manager_id' => null, 'parent_id' => null]);
            DB::table('departments')->truncate();
        }

        Schema::enableForeignKeyConstraints();

        User::query()
            ->withTrashed()
            ->where('email', '!=', $adminEmail)
            ->get()
            ->each(function (User $user): void {
                $user->forceDelete();
            });

        Company::query()
            ->withTrashed()
            ->whereKeyNot($keep->id)
            ->get()
            ->each(function (Company $company): void {
                $company->forceDelete();
            });
    }

    /**
     * @return array{manager: User, frontdesk: User, housekeeping: User, maintenance: User, admin: User}
     */
    protected function seedStaff(Company $company, Workspace $workspace, User $admin): array
    {
        $password = (string) config('saas.seed.admin_password');

        $definitions = [
            'manager' => [
                'name' => 'Nadia Rahman',
                'first_name' => 'Nadia',
                'last_name' => 'Rahman',
                'email' => 'manager@example.com',
                'job_title' => 'Hotel Manager',
                'role' => 'manager',
                'company_role' => CompanyRole::Admin,
                'workspace_role' => WorkspaceMemberRole::Manager,
            ],
            'frontdesk' => [
                'name' => 'Kamal Hassan',
                'first_name' => 'Kamal',
                'last_name' => 'Hassan',
                'email' => 'frontdesk@example.com',
                'job_title' => 'Front Desk Agent',
                'role' => 'member',
                'company_role' => CompanyRole::Member,
                'workspace_role' => WorkspaceMemberRole::Member,
            ],
            'housekeeping' => [
                'name' => 'Aisha Begum',
                'first_name' => 'Aisha',
                'last_name' => 'Begum',
                'email' => 'housekeeping@example.com',
                'job_title' => 'Housekeeping Supervisor',
                'role' => 'member',
                'company_role' => CompanyRole::Member,
                'workspace_role' => WorkspaceMemberRole::Member,
            ],
            'maintenance' => [
                'name' => 'Rafi Chowdhury',
                'first_name' => 'Rafi',
                'last_name' => 'Chowdhury',
                'email' => 'maintenance@example.com',
                'job_title' => 'Maintenance Technician',
                'role' => 'member',
                'company_role' => CompanyRole::Member,
                'workspace_role' => WorkspaceMemberRole::Member,
            ],
        ];

        $staff = ['admin' => $admin];

        foreach ($definitions as $key => $definition) {
            $user = User::query()->create([
                'name' => $definition['name'],
                'first_name' => $definition['first_name'],
                'last_name' => $definition['last_name'],
                'email' => $definition['email'],
                'password' => $password,
                'job_title' => $definition['job_title'],
                'status' => UserStatus::Active,
                'timezone' => 'Asia/Dhaka',
                'locale' => 'en',
                'theme' => Theme::System,
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
                'current_company_id' => $company->id,
                'current_workspace_id' => $workspace->id,
            ])->save();

            $user->assignRole($definition['role']);

            $company->members()->syncWithoutDetaching([
                $user->id => [
                    'role' => $definition['company_role']->value,
                    'job_title' => $definition['job_title'],
                    'joined_at' => now()->subMonths(4),
                ],
            ]);

            $workspace->members()->syncWithoutDetaching([
                $user->id => [
                    'role' => $definition['workspace_role']->value,
                    'status' => WorkspaceMemberStatus::Active->value,
                    'joined_at' => now()->subMonths(4),
                ],
            ]);

            $staff[$key] = $user;
        }

        $admin->forceFill([
            'current_company_id' => $company->id,
            'current_workspace_id' => $workspace->id,
            'job_title' => 'General Manager',
        ])->save();

        $company->members()->syncWithoutDetaching([
            $admin->id => [
                'role' => CompanyRole::Owner->value,
                'job_title' => 'General Manager',
                'joined_at' => now()->subYear(),
            ],
        ]);

        return $staff;
    }

    /**
     * @param  array{manager: User, frontdesk: User, housekeeping: User, maintenance: User, admin: User}  $staff
     */
    protected function seedDepartments(Company $company, array $staff): void
    {
        $departments = [
            ['name' => 'Front Office', 'manager' => $staff['frontdesk'], 'team' => 'Reception', 'color' => '#2563eb'],
            ['name' => 'Housekeeping', 'manager' => $staff['housekeeping'], 'team' => 'Room Attendants', 'color' => '#059669'],
            ['name' => 'Food & Beverage', 'manager' => $staff['manager'], 'team' => 'Restaurant Floor', 'color' => '#d97706'],
            ['name' => 'Maintenance', 'manager' => $staff['maintenance'], 'team' => 'Engineering', 'color' => '#7c3aed'],
            ['name' => 'Security', 'manager' => $staff['manager'], 'team' => 'Night Watch', 'color' => '#475569'],
        ];

        foreach ($departments as $row) {
            $department = Department::query()->create([
                'manager_id' => $row['manager']->id,
                'name' => $row['name'],
                'description' => "{$row['name']} operations for Grand Palms Hotel.",
            ]);

            $team = Team::query()->create([
                'department_id' => $department->id,
                'lead_id' => $row['manager']->id,
                'name' => $row['team'],
                'color' => $row['color'],
                'description' => "{$row['team']} team.",
            ]);

            $team->members()->syncWithoutDetaching([$row['manager']->id, $staff['admin']->id]);

            $company->members()->updateExistingPivot($row['manager']->id, [
                'department_id' => $department->id,
            ]);
        }
    }

    protected function seedProperty(Company $company): Hotel
    {
        $hotel = Hotel::query()->create([
            'name' => 'Grand Palms Hotel',
            'slug' => 'grand-palms-hotel',
            'description' => 'A 12-key boutique hotel in Gulshan with city and garden rooms, a restaurant, and a rooftop pool.',
            'address' => '12 Gulshan Avenue',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'country' => 'Bangladesh',
            'postal_code' => '1212',
            'phone' => '+880 2 5500 1200',
            'email' => 'stay@grandpalms.example',
            'website' => 'https://grandpalms.example',
            'check_in_time' => '14:00',
            'check_out_time' => '11:00',
            'currency' => $company->currency ?? 'USD',
            'timezone' => 'Asia/Dhaka',
            'tax_rate' => 0.1000,
            'tax_name' => 'VAT',
            'policies' => 'Check-in from 14:00. Check-out by 11:00. Photo ID required at arrival. Pets are not permitted.',
            'contact_name' => 'Nadia Rahman',
            'contact_phone' => '+880 1711 220011',
            'contact_email' => 'manager@example.com',
            'status' => HotelStatus::Active,
            'is_active' => true,
        ]);

        $this->attachPublicImage($hotel, 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1600&q=80', 'cover');

        $building = Building::query()->create([
            'hotel_id' => $hotel->id,
            'name' => 'Main Tower',
            'code' => 'MT',
            'description' => 'Guest rooms, lobby, and F&B outlets.',
            'is_active' => true,
        ]);

        $floors = [];

        foreach ([
            ['name' => 'Ground Floor', 'number' => 0, 'code' => 'G'],
            ['name' => 'Floor 1', 'number' => 1, 'code' => 'L1'],
            ['name' => 'Floor 2', 'number' => 2, 'code' => 'L2'],
            ['name' => 'Floor 3', 'number' => 3, 'code' => 'L3'],
        ] as $floorRow) {
            $floors[$floorRow['number']] = Floor::query()->create([
                'hotel_id' => $hotel->id,
                'building_id' => $building->id,
                'name' => $floorRow['name'],
                'floor_number' => $floorRow['number'],
                'code' => $floorRow['code'],
                'is_active' => true,
            ]);
        }

        $types = [];

        foreach ([
            ['name' => 'Standard Twin', 'code' => 'STW', 'price' => 8500, 'adults' => 2, 'children' => 1, 'occupancy' => 3, 'beds' => '2 single beds', 'description' => 'Two singles facing the avenue or the garden court. Desk, blackout curtains and a compact bath. Sleeps two adults and one child.', 'image' => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=1400&q=80'],
            ['name' => 'Deluxe King', 'code' => 'DLX', 'price' => 12500, 'adults' => 2, 'children' => 1, 'occupancy' => 3, 'beds' => '1 king bed', 'description' => 'A king bed, seating nook and city or garden outlook. The most requested room for couples and short business stays.', 'image' => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=1400&q=80'],
            ['name' => 'Family Room', 'code' => 'FAM', 'price' => 17500, 'adults' => 3, 'children' => 2, 'occupancy' => 5, 'beds' => '1 king + 2 singles', 'description' => 'King plus two singles on the second floor. Extra space for luggage and a sofa for evenings in.', 'image' => 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1400&q=80'],
            ['name' => 'Executive Suite', 'code' => 'STE', 'price' => 24000, 'adults' => 2, 'children' => 2, 'occupancy' => 4, 'beds' => '1 king bed + sofa', 'description' => 'Top-floor suite with a sitting room, king bed and sofa bed. Spa credit can be posted to the folio.', 'image' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1400&q=80'],
        ] as $typeRow) {
            $types[$typeRow['code']] = RoomType::query()->create([
                'hotel_id' => $hotel->id,
                'name' => $typeRow['name'],
                'code' => $typeRow['code'],
                'description' => $typeRow['description'],
                'base_price' => $typeRow['price'],
                'max_adults' => $typeRow['adults'],
                'max_children' => $typeRow['children'],
                'max_occupancy' => $typeRow['occupancy'],
                'bed_configuration' => $typeRow['beds'],
                'is_active' => true,
            ]);

            $this->attachPublicImage($types[$typeRow['code']], $typeRow['image']);
        }

        $roomLayout = [
            ['number' => '101', 'floor' => 1, 'type' => 'STW'],
            ['number' => '102', 'floor' => 1, 'type' => 'STW'],
            ['number' => '103', 'floor' => 1, 'type' => 'DLX'],
            ['number' => '104', 'floor' => 1, 'type' => 'DLX'],
            ['number' => '201', 'floor' => 2, 'type' => 'DLX'],
            ['number' => '202', 'floor' => 2, 'type' => 'DLX'],
            ['number' => '203', 'floor' => 2, 'type' => 'FAM'],
            ['number' => '204', 'floor' => 2, 'type' => 'FAM'],
            ['number' => '301', 'floor' => 3, 'type' => 'STE'],
            ['number' => '302', 'floor' => 3, 'type' => 'STE'],
            ['number' => '303', 'floor' => 3, 'type' => 'DLX'],
            ['number' => '304', 'floor' => 3, 'type' => 'STW'],
        ];

        foreach ($roomLayout as $row) {
            $type = $types[$row['type']];
            $floor = $floors[$row['floor']];

            $room = Room::query()->create([
                'hotel_id' => $hotel->id,
                'building_id' => $building->id,
                'floor_id' => $floor->id,
                'room_type_id' => $type->id,
                'number' => $row['number'],
                'code' => 'GP-'.$row['number'],
                'base_price' => $type->base_price,
                'max_occupancy' => $type->max_occupancy,
                'status' => RoomStatus::Available,
                'is_active' => true,
            ]);

            $this->seedBeds($hotel, $room, $floor, $type->code, $type->base_price);
        }

        $facilities = [
            ['name' => 'Free Wi-Fi', 'code' => 'WIFI', 'icon' => 'wifi'],
            ['name' => 'Swimming Pool', 'code' => 'POOL', 'icon' => 'waves'],
            ['name' => 'Fitness Centre', 'code' => 'GYM', 'icon' => 'dumbbell'],
            ['name' => 'On-site Restaurant', 'code' => 'FNB', 'icon' => 'utensils'],
            ['name' => 'Airport Shuttle', 'code' => 'TRN', 'icon' => 'bus'],
            ['name' => 'Spa', 'code' => 'SPA', 'icon' => 'sparkles'],
            ['name' => 'Air Conditioning', 'code' => 'AC', 'icon' => 'wind'],
            ['name' => 'Room Safe', 'code' => 'SAFE', 'icon' => 'lock'],
        ];

        foreach ($facilities as $facilityRow) {
            $facility = Facility::query()->create([
                'hotel_id' => $hotel->id,
                'name' => $facilityRow['name'],
                'code' => $facilityRow['code'],
                'icon' => $facilityRow['icon'],
                'is_active' => true,
            ]);

            $hotel->amenityFacilities()->syncWithoutDetaching([$facility->id]);
        }

        foreach ($types as $type) {
            $type->facilities()->sync(
                Facility::query()
                    ->where('hotel_id', $hotel->id)
                    ->whereIn('code', $type->code === 'STE' ? ['WIFI', 'AC', 'SAFE', 'SPA'] : ['WIFI', 'AC', 'SAFE'])
                    ->pluck('id')
                    ->all(),
            );
        }

        BookingSetting::query()->create([
            'hotel_id' => $hotel->id,
            'is_enabled' => true,
            'public_slug' => 'grand-palms',
            'min_advance_days' => 0,
            'max_advance_days' => 365,
            'require_deposit' => true,
            'deposit_amount' => 5000,
        ]);

        app(SeedBookingPaymentMethods::class)->handle((int) $hotel->company_id);

        return $hotel->fresh(['rooms.roomType', 'rooms.floor', 'rooms.beds']) ?? $hotel;
    }

    protected function seedBeds(Hotel $hotel, Room $room, Floor $floor, string $typeCode, int $roomPrice): void
    {
        $beds = match ($typeCode) {
            'STW' => [
                ['name' => 'Bed A', 'type' => 'single', 'price' => intdiv($roomPrice, 2)],
                ['name' => 'Bed B', 'type' => 'single', 'price' => intdiv($roomPrice, 2)],
            ],
            'FAM' => [
                ['name' => 'King', 'type' => 'king', 'price' => 10000],
                ['name' => 'Single 1', 'type' => 'single', 'price' => 3750],
                ['name' => 'Single 2', 'type' => 'single', 'price' => 3750],
            ],
            default => [
                ['name' => 'King', 'type' => 'king', 'price' => $roomPrice],
            ],
        };

        foreach ($beds as $bed) {
            Bed::query()->create([
                'hotel_id' => $hotel->id,
                'room_id' => $room->id,
                'floor_id' => $floor->id,
                'name' => $bed['name'],
                'bed_type' => $bed['type'],
                'price' => $bed['price'],
                'status' => BedStatus::Available,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @param  array{manager: User, frontdesk: User, housekeeping: User, maintenance: User, admin: User}  $staff
     */
    protected function seedOperations(Hotel $hotel, array $staff, User $admin): void
    {
        $services = $this->seedServices($hotel);
        $rooms = $hotel->rooms->keyBy('number');

        $guests = collect([
            ['first' => 'James', 'last' => 'Carter', 'gender' => GuestGender::Male, 'email' => 'james.carter@example.com', 'phone' => '+1 415 555 0198', 'city' => 'San Francisco', 'country' => 'United States', 'vip' => true],
            ['first' => 'Priya', 'last' => 'Sen', 'gender' => GuestGender::Female, 'email' => 'priya.sen@example.com', 'phone' => '+91 98200 11223', 'city' => 'Kolkata', 'country' => 'India', 'vip' => false],
            ['first' => 'Omar', 'last' => 'Faruk', 'gender' => GuestGender::Male, 'email' => 'omar.faruk@example.com', 'phone' => '+880 1712 334455', 'city' => 'Chattogram', 'country' => 'Bangladesh', 'vip' => false],
            ['first' => 'Elena', 'last' => 'Rossi', 'gender' => GuestGender::Female, 'email' => 'elena.rossi@example.com', 'phone' => '+39 347 220 1188', 'city' => 'Milan', 'country' => 'Italy', 'vip' => false],
            ['first' => 'Daniel', 'last' => 'Okeke', 'gender' => GuestGender::Male, 'email' => 'daniel.okeke@example.com', 'phone' => '+234 803 441 2290', 'city' => 'Lagos', 'country' => 'Nigeria', 'vip' => false],
            ['first' => 'Mei', 'last' => 'Lin', 'gender' => GuestGender::Female, 'email' => 'mei.lin@example.com', 'phone' => '+65 8123 4411', 'city' => 'Singapore', 'country' => 'Singapore', 'vip' => true],
            ['first' => 'Hassan', 'last' => 'Ali', 'gender' => GuestGender::Male, 'email' => 'hassan.ali@example.com', 'phone' => '+971 50 221 0987', 'city' => 'Dubai', 'country' => 'United Arab Emirates', 'vip' => false],
            ['first' => 'Sofia', 'last' => 'Martinez', 'gender' => GuestGender::Female, 'email' => 'sofia.martinez@example.com', 'phone' => '+34 612 440 118', 'city' => 'Barcelona', 'country' => 'Spain', 'vip' => false],
        ])->map(fn (array $row): Guest => Guest::query()->create([
            'hotel_id' => $hotel->id,
            'first_name' => $row['first'],
            'last_name' => $row['last'],
            'gender' => $row['gender'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'city' => $row['city'],
            'country' => $row['country'],
            'nationality' => $row['country'],
            'id_type' => 'passport',
            'id_number' => 'P'.Str::upper(Str::random(8)),
            'is_vip' => $row['vip'],
        ]));

        $inHouseRoom = $rooms['201'];
        $inHouse = app(CreateReservation::class)->handle(new ReservationData(
            hotelId: $hotel->id,
            guestId: $guests[0]->id,
            checkInDate: now()->toDateString(),
            checkOutDate: now()->addDays(3)->toDateString(),
            roomId: $inHouseRoom->id,
            roomTypeId: $inHouseRoom->room_type_id,
            adults: 2,
            bookingSource: BookingSource::WalkIn,
            specialRequests: 'Late check-out if possible.',
            tax: 3750,
            status: ReservationStatus::Confirmed,
        ));

        $inHouse = app(CheckInReservation::class)->handle($inHouse, ['paid_amount' => 15000]);
        $folio = $inHouse->fresh(['guestFolio'])?->guestFolio;

        if ($folio instanceof GuestFolio) {
            app(AddFolioItem::class)->handle($folio, new FolioItemData(
                hotelServiceId: $services['BRK']->id,
                quantity: 2,
            ));
            app(AddFolioItem::class)->handle($folio, new FolioItemData(
                hotelServiceId: $services['LND']->id,
                quantity: 1,
            ));
        }

        $arrivingRoom = $rooms['103'];
        app(CreateReservation::class)->handle(new ReservationData(
            hotelId: $hotel->id,
            guestId: $guests[1]->id,
            checkInDate: now()->toDateString(),
            checkOutDate: now()->addDays(2)->toDateString(),
            roomId: $arrivingRoom->id,
            roomTypeId: $arrivingRoom->room_type_id,
            adults: 2,
            children: 1,
            bookingSource: BookingSource::BookingCom,
            externalReference: 'BCOM-88421',
            tax: 2500,
            status: ReservationStatus::Confirmed,
        ));

        app(CreateReservation::class)->handle(new ReservationData(
            hotelId: $hotel->id,
            guestId: $guests[2]->id,
            checkInDate: now()->addDay()->toDateString(),
            checkOutDate: now()->addDays(4)->toDateString(),
            roomTypeId: $rooms['301']->room_type_id,
            adults: 2,
            bookingSource: BookingSource::Website,
            specialRequests: 'High floor, away from lift.',
            tax: 7200,
            status: ReservationStatus::Pending,
        ));

        $futureRoom = $rooms['203'];
        app(CreateReservation::class)->handle(new ReservationData(
            hotelId: $hotel->id,
            guestId: $guests[5]->id,
            checkInDate: now()->addDays(5)->toDateString(),
            checkOutDate: now()->addDays(8)->toDateString(),
            roomId: $futureRoom->id,
            roomTypeId: $futureRoom->room_type_id,
            adults: 2,
            children: 2,
            bookingSource: BookingSource::Agoda,
            externalReference: 'AGD-12009',
            tax: 5250,
            status: ReservationStatus::Confirmed,
        ));

        app(CreateReservation::class)->handle(new ReservationData(
            hotelId: $hotel->id,
            guestId: $guests[6]->id,
            checkInDate: now()->addDays(2)->toDateString(),
            checkOutDate: now()->addDays(5)->toDateString(),
            roomTypeId: $rooms['102']->room_type_id,
            adults: 1,
            bookingSource: BookingSource::Phone,
            status: ReservationStatus::Cancelled,
        ));

        $this->seedPastStay($hotel, $rooms['104'], $guests[3], $admin, $services);

        $this->seedHousekeeping($hotel, $rooms, $inHouse, $staff);
        $this->seedMaintenance($hotel, $rooms['304'], $staff);
        $this->seedRestaurants($hotel, $inHouse, $folio);
    }

    /**
     * @return array<string, HotelService>
     */
    protected function seedServices(Hotel $hotel): array
    {
        $catalog = [
            ['code' => 'BRK', 'name' => 'Breakfast', 'category' => HotelServiceCategory::Food, 'price' => 1800, 'tax' => 10],
            ['code' => 'LND', 'name' => 'Laundry', 'category' => HotelServiceCategory::Laundry, 'price' => 1200, 'tax' => 5],
            ['code' => 'AIR', 'name' => 'Airport Transfer', 'category' => HotelServiceCategory::Transport, 'price' => 3500, 'tax' => 0],
            ['code' => 'SPA', 'name' => 'Spa Massage', 'category' => HotelServiceCategory::Spa, 'price' => 6500, 'tax' => 10],
            ['code' => 'MIN', 'name' => 'Mini Bar Restock', 'category' => HotelServiceCategory::Room, 'price' => 900, 'tax' => 10],
            ['code' => 'XBD', 'name' => 'Extra Bed', 'category' => HotelServiceCategory::Room, 'price' => 2500, 'tax' => 10],
        ];

        $services = [];

        foreach ($catalog as $row) {
            $services[$row['code']] = HotelService::query()->create([
                'hotel_id' => $hotel->id,
                'name' => $row['name'],
                'code' => $row['code'],
                'category' => $row['category'],
                'price' => $row['price'],
                'tax_rate' => $row['tax'],
                'is_active' => true,
            ]);
        }

        return $services;
    }

    /**
     * @param  array<string, HotelService>  $services
     */
    protected function seedPastStay(Hotel $hotel, Room $room, Guest $guest, User $admin, array $services): void
    {
        $nights = 2;
        $subtotal = ($room->base_price ?? 12500) * $nights;
        $tax = (int) intdiv($subtotal, 10);
        $total = $subtotal + $tax;

        $reservation = new Reservation([
            'hotel_id' => $hotel->id,
            'number' => 'RSV-PAST-0001',
            'guest_id' => $guest->id,
            'room_id' => $room->id,
            'room_type_id' => $room->room_type_id,
            'check_in_date' => now()->subDays(5)->toDateString(),
            'check_out_date' => now()->subDays(3)->toDateString(),
            'checked_in_at' => now()->subDays(5)->setTime(14, 20),
            'checked_out_at' => now()->subDays(3)->setTime(10, 45),
            'adults' => 2,
            'booking_source' => BookingSource::Expedia,
            'external_reference' => 'EXP-44102',
            'tax' => $tax,
            'total' => $total,
            'paid_amount' => $total,
            'due_amount' => 0,
            'status' => ReservationStatus::CheckedOut,
        ]);
        $reservation->save();

        $folio = new GuestFolio([
            'hotel_id' => $hotel->id,
            'guest_id' => $guest->id,
            'reservation_id' => $reservation->id,
            'number' => 'FOL-PAST-0001',
            'status' => FolioStatus::Closed,
            'currency' => $hotel->currency,
            'opened_at' => $reservation->checked_in_at,
            'closed_at' => $reservation->checked_out_at,
        ]);
        $folio->save();

        $folio->items()->create([
            'type' => FolioItemType::Room,
            'description' => "Room {$room->number} — {$nights} night(s)",
            'quantity' => $nights,
            'unit_price' => $room->base_price ?? 12500,
            'amount' => $subtotal,
            'posted_at' => $reservation->checked_in_at,
            'posted_by' => $admin->id,
        ]);

        $folio->items()->create([
            'hotel_service_id' => $services['AIR']->id,
            'type' => FolioItemType::Service,
            'description' => 'Airport Transfer',
            'quantity' => 1,
            'unit_price' => $services['AIR']->price,
            'amount' => $services['AIR']->price,
            'posted_at' => $reservation->checked_in_at,
            'posted_by' => $admin->id,
        ]);

        app(FolioCalculator::class)->recalculate($folio);

        $payment = new GuestPayment([
            'guest_folio_id' => $folio->id,
            'guest_id' => $guest->id,
            'amount' => $folio->fresh()?->total ?? $total,
            'currency' => $hotel->currency,
            'method' => GuestPaymentMethod::Card,
            'status' => GuestPaymentStatus::Completed,
            'reference' => 'CARD-8821',
            'paid_at' => $reservation->checked_out_at,
            'recorded_by' => $admin->id,
        ]);
        $payment->save();

        app(FolioCalculator::class)->recalculate($folio->fresh() ?? $folio);
        app(GenerateGuestInvoice::class)->handle($folio->fresh() ?? $folio);

        $room->update(['status' => RoomStatus::Available]);
    }

    /**
     * @param  Collection<string, Room>  $rooms
     * @param  array{housekeeping: User, admin: User}  $staff
     */
    protected function seedHousekeeping(Hotel $hotel, $rooms, Reservation $inHouse, array $staff): void
    {
        $checkout = app(CreateHousekeepingTask::class);
        $stayover = $checkout->handle(new HousekeepingTaskData(
            hotelId: $hotel->id,
            roomId: $inHouse->room_id ?? $rooms['201']->id,
            reservationId: $inHouse->id,
            priority: HousekeepingPriority::Normal->value,
            taskType: HousekeepingTaskType::Stayover->value,
            assignedTo: $staff['housekeeping']->id,
            instructions: 'Stayover service for in-house guest. Do not disturb before 10:00.',
            scheduledFor: now()->toDateString(),
        ));

        HousekeepingTask::query()->whereKey($stayover->id)->update([
            'status' => HousekeepingTaskStatus::InProgress->value,
            'started_at' => now()->subHour(),
        ]);

        $dirty = $checkout->handle(new HousekeepingTaskData(
            hotelId: $hotel->id,
            roomId: $rooms['102']->id,
            priority: HousekeepingPriority::High->value,
            taskType: HousekeepingTaskType::Checkout->value,
            assignedTo: $staff['housekeeping']->id,
            instructions: 'Vacant dirty — full checkout clean and linen change.',
            scheduledFor: now()->toDateString(),
        ));

        $rooms['102']->update(['status' => RoomStatus::Dirty]);

        $done = $checkout->handle(new HousekeepingTaskData(
            hotelId: $hotel->id,
            roomId: $rooms['101']->id,
            priority: HousekeepingPriority::Normal->value,
            taskType: HousekeepingTaskType::DeepClean->value,
            assignedTo: $staff['housekeeping']->id,
            instructions: 'Weekly deep clean completed.',
            scheduledFor: now()->subDay()->toDateString(),
        ));

        HousekeepingTask::query()->whereKey($done->id)->update([
            'status' => HousekeepingTaskStatus::Completed->value,
            'started_at' => now()->subDay()->setTime(9, 0),
            'completed_at' => now()->subDay()->setTime(11, 30),
        ]);

        unset($dirty);
    }

    /**
     * @param  array{maintenance: User, frontdesk: User}  $staff
     */
    protected function seedMaintenance(Hotel $hotel, Room $room, array $staff): void
    {
        app(CreateMaintenanceRequest::class)->handle(new MaintenanceRequestData(
            hotelId: $hotel->id,
            roomId: $room->id,
            title: 'AC not cooling',
            description: 'Guest reported weak airflow. Likely filter or compressor issue.',
            category: MaintenanceCategory::Hvac->value,
            priority: MaintenancePriority::High->value,
            blocksRoom: true,
            assignedTo: $staff['maintenance']->id,
            dueAt: now()->addDay()->toIso8601String(),
        ));

        $open = app(CreateMaintenanceRequest::class)->handle(new MaintenanceRequestData(
            hotelId: $hotel->id,
            roomId: $hotel->rooms->firstWhere('number', '202')?->id,
            title: 'Bathroom tap drip',
            description: 'Slow drip from the basin mixer. Does not block the room.',
            category: MaintenanceCategory::Plumbing->value,
            priority: MaintenancePriority::Normal->value,
            blocksRoom: false,
            assignedTo: $staff['maintenance']->id,
        ));

        MaintenanceRequest::query()->whereKey($open->id)->update([
            'status' => MaintenanceRequestStatus::InProgress->value,
            'started_at' => now()->subMinutes(40),
            'reported_by' => $staff['frontdesk']->id,
        ]);
    }

    protected function seedRestaurants(Hotel $hotel, Reservation $inHouse, ?GuestFolio $folio): void
    {
        $restaurant = Restaurant::query()->create([
            'hotel_id' => $hotel->id,
            'name' => 'The Palms Restaurant',
            'code' => 'PALMS',
            'description' => 'All-day dining overlooking the garden.',
            'is_active' => true,
        ]);

        Restaurant::query()->create([
            'hotel_id' => $hotel->id,
            'name' => 'Pool Bar',
            'code' => 'POOL',
            'description' => 'Light bites and drinks by the rooftop pool.',
            'is_active' => true,
        ]);

        PosOrder::query()->create([
            'restaurant_id' => $restaurant->id,
            'reservation_id' => $inHouse->id,
            'guest_folio_id' => $folio?->id,
            'number' => 'POS-'.now()->format('Ymd').'-0001',
            'status' => PosOrderStatus::Closed,
            'total' => 4200,
            'currency' => $hotel->currency,
            'notes' => 'Room charge to 201 — two breakfasts.',
            'opened_at' => now()->subHours(3),
            'closed_at' => now()->subHours(2),
        ]);

        PosOrder::query()->create([
            'restaurant_id' => $restaurant->id,
            'number' => 'POS-'.now()->format('Ymd').'-0002',
            'status' => PosOrderStatus::Open,
            'total' => 1850,
            'currency' => $hotel->currency,
            'notes' => 'Walk-in lunch, table 4.',
            'opened_at' => now()->subMinutes(25),
        ]);
    }

    protected function attachPublicImage(Hotel|RoomType $model, string $url, string $collection = 'gallery'): void
    {
        try {
            $model->addMediaFromUrl($url)->toMediaCollection($collection);
        } catch (Throwable) {
            // Seed still succeeds offline or if the image host is unreachable.
        }
    }
}
