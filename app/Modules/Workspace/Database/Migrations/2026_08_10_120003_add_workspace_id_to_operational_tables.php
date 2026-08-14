<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    protected array $workspaceTables = [
        'hotels',
        'hotel_buildings',
        'hotel_floors',
        'room_types',
        'rooms',
        'beds',
        'facilities',
        'guests',
        'reservations',
        'hotel_services',
        'guest_folios',
        'guest_payments',
        'guest_invoices',
        'housekeeping_tasks',
        'maintenance_requests',
        'booking_settings',
        'restaurants',
        'pos_orders',
    ];

    public function up(): void
    {
        foreach ($this->workspaceTables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'workspace_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('workspace_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('workspaces')
                    ->cascadeOnDelete();
            });
        }

        $this->seedDefaultWorkspaces();
        $this->backfillWorkspaceIds();
    }

    public function down(): void
    {
        foreach ($this->workspaceTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'workspace_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('workspace_id');
            });
        }
    }

    protected function seedDefaultWorkspaces(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('workspaces')) {
            return;
        }

        $defaultName = (string) config('saas.operations.default_name', 'Default Workspace');

        foreach (DB::table('companies')->orderBy('id')->get() as $company) {
            $exists = DB::table('workspaces')
                ->where('company_id', $company->id)
                ->where('is_default', true)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('workspaces')->insert([
                'company_id' => $company->id,
                'uuid' => (string) Str::ulid(),
                'name' => $defaultName,
                'slug' => 'default',
                'status' => 'active',
                'is_default' => true,
                'timezone' => $company->timezone ?? null,
                'currency' => $company->currency ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function backfillWorkspaceIds(): void
    {
        if (! Schema::hasTable('workspaces')) {
            return;
        }

        $defaults = DB::table('workspaces')
            ->where('is_default', true)
            ->pluck('id', 'company_id');

        if ($defaults->isEmpty()) {
            return;
        }

        if (Schema::hasTable('hotels')) {
            foreach ($defaults as $companyId => $workspaceId) {
                DB::table('hotels')
                    ->where('company_id', $companyId)
                    ->whereNull('workspace_id')
                    ->update(['workspace_id' => $workspaceId]);
            }
        }

        $hotelScoped = [
            'hotel_buildings', 'hotel_floors', 'room_types', 'rooms', 'beds',
            'reservations', 'guest_folios', 'guest_payments',
            'guest_invoices', 'housekeeping_tasks',
            'maintenance_requests', 'booking_settings', 'restaurants', 'pos_orders',
        ];

        foreach ($hotelScoped as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'hotel_id')) {
                continue;
            }

            DB::table($table)
                ->whereNull('workspace_id')
                ->whereNotNull('hotel_id')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table): void {
                    foreach ($rows as $row) {
                        $workspaceId = DB::table('hotels')->where('id', $row->hotel_id)->value('workspace_id');

                        if ($workspaceId !== null) {
                            DB::table($table)->where('id', $row->id)->update(['workspace_id' => $workspaceId]);
                        }
                    }
                });
        }

        if (Schema::hasTable('facilities')) {
            foreach ($defaults as $companyId => $workspaceId) {
                DB::table('facilities')
                    ->where('company_id', $companyId)
                    ->whereNull('workspace_id')
                    ->whereNull('hotel_id')
                    ->update(['workspace_id' => $workspaceId]);

                DB::table('facilities')
                    ->where('company_id', $companyId)
                    ->whereNull('workspace_id')
                    ->whereNotNull('hotel_id')
                    ->orderBy('id')
                    ->chunkById(500, function ($rows): void {
                        foreach ($rows as $row) {
                            $workspaceId = DB::table('hotels')->where('id', $row->hotel_id)->value('workspace_id');

                            if ($workspaceId !== null) {
                                DB::table('facilities')->where('id', $row->id)->update(['workspace_id' => $workspaceId]);
                            }
                        }
                    });
            }
        }

        if (Schema::hasTable('guests')) {
            foreach ($defaults as $companyId => $workspaceId) {
                DB::table('guests')
                    ->where('company_id', $companyId)
                    ->whereNull('workspace_id')
                    ->whereNull('hotel_id')
                    ->update(['workspace_id' => $workspaceId]);

                DB::table('guests')
                    ->where('company_id', $companyId)
                    ->whereNull('workspace_id')
                    ->whereNotNull('hotel_id')
                    ->orderBy('id')
                    ->chunkById(500, function ($rows): void {
                        foreach ($rows as $row) {
                            $workspaceId = DB::table('hotels')->where('id', $row->hotel_id)->value('workspace_id');

                            if ($workspaceId !== null) {
                                DB::table('guests')->where('id', $row->id)->update(['workspace_id' => $workspaceId]);
                            }
                        }
                    });
            }
        }

        if (Schema::hasTable('hotel_services')) {
            foreach ($defaults as $companyId => $workspaceId) {
                DB::table('hotel_services')
                    ->where('company_id', $companyId)
                    ->whereNull('workspace_id')
                    ->whereNull('hotel_id')
                    ->update(['workspace_id' => $workspaceId]);

                DB::table('hotel_services')
                    ->where('company_id', $companyId)
                    ->whereNull('workspace_id')
                    ->whereNotNull('hotel_id')
                    ->orderBy('id')
                    ->chunkById(500, function ($rows): void {
                        foreach ($rows as $row) {
                            $workspaceId = DB::table('hotels')->where('id', $row->hotel_id)->value('workspace_id');

                            if ($workspaceId !== null) {
                                DB::table('hotel_services')->where('id', $row->id)->update(['workspace_id' => $workspaceId]);
                            }
                        }
                    });
            }
        }
    }
};
