<?php

declare(strict_types=1);

namespace App\Modules\User\Exports;

use App\Modules\User\Models\User;
use App\Support\DataTable\TableBuilder;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Excel;

/**
 * Exports exactly the rows the user is looking at.
 *
 * The query is handed in from {@see TableBuilder::exportQuery()},
 * so the on-screen search, filters and sort are already applied and the export
 * can never silently widen the result set.
 *
 * @implements WithMapping<User>
 */
class UsersExport implements FromQuery, WithHeadings, WithMapping
{
    public const FORMATS = [
        'csv' => Excel::CSV,
        'xlsx' => Excel::XLSX,
    ];

    /**
     * @param  Builder<User>  $query
     */
    public function __construct(protected Builder $query) {}

    public static function writerType(string $format): string
    {
        return self::FORMATS[$format] ?? Excel::XLSX;
    }

    public static function filename(string $format): string
    {
        return 'users-'.now()->format('Y-m-d-His').'.'.(array_key_exists($format, self::FORMATS) ? $format : 'xlsx');
    }

    /**
     * @return Builder<User>
     */
    public function query(): Builder
    {
        return $this->query->with('roles');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            __('Name'),
            __('Email'),
            __('Job title'),
            __('Status'),
            __('Roles'),
            __('Last login'),
            __('Created'),
        ];
    }

    /**
     * @param  User  $row
     * @return list<string|null>
     */
    public function map($row): array
    {
        return [
            $row->name,
            $row->email,
            $row->job_title,
            $row->status->label(),
            $row->roles->pluck('name')->implode(', '),
            $row->last_login_at?->toDateTimeString(),
            $row->created_at?->toDateTimeString(),
        ];
    }
}
