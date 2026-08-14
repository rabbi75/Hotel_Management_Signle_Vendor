<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Widgets;

use App\Modules\Dashboard\Enums\WidgetSize;
use App\Modules\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;

/**
 * The activity log, narrowed to people in this workspace.
 *
 * spatie/laravel-activitylog has no tenant column, so the feed is scoped by
 * causer instead: entries caused by a member of the active workspace.
 */
class RecentActivityWidget extends Widget
{
    protected const LIMIT = 10;

    public function key(): string
    {
        return 'recent-activity';
    }

    public function title(): string
    {
        return (string) __('Recent activity');
    }

    public function permission(): ?string
    {
        return 'audit.activity.view';
    }

    public function defaultSize(): WidgetSize
    {
        return WidgetSize::Medium;
    }

    public function defaultOrder(): int
    {
        return 40;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $companyId = current_company_id();
        $table = (string) config('activitylog.table_name', 'activity_log');

        if ($companyId === null || ! Schema::hasTable($table)) {
            return ['items' => []];
        }

        $memberIds = DB::table('company_user')->where('company_id', $companyId)->pluck('user_id');

        $rows = DB::table($table)
            ->whereIn('causer_id', $memberIds)
            ->where('causer_type', User::class)
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get(['id', 'log_name', 'description', 'subject_type', 'causer_id', 'created_at']);

        $names = DB::table('users')->whereIn('id', $memberIds)->pluck('name', 'id');

        return [
            'items' => $rows->map(static function (stdClass $row) use ($names): array {
                $createdAt = Carbon::parse((string) $row->created_at);

                return [
                    'id' => (int) $row->id,
                    'log_name' => is_string($row->log_name) ? $row->log_name : null,
                    'description' => (string) $row->description,
                    'subject' => is_string($row->subject_type) ? class_basename($row->subject_type) : null,
                    'causer' => $names[$row->causer_id] ?? null,
                    'created_at' => $createdAt->toIso8601String(),
                    'created_at_human' => $createdAt->diffForHumans(),
                ];
            })->values()->all(),
        ];
    }
}
