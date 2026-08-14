<?php

declare(strict_types=1);

namespace App\Modules\Audit\Exports;

use App\Modules\Audit\Models\SecurityLog;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<SecurityLog>
 */
class SecurityLogExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Builder<SecurityLog>  $query
     */
    public function __construct(protected Builder $query) {}

    /**
     * @return Builder<SecurityLog>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['ID', 'Event', 'Severity', 'Description', 'User', 'IP address', 'Context', 'Recorded at'];
    }

    /**
     * @param  SecurityLog  $row
     * @return list<string|int|null>
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->event->value,
            $row->severity->value,
            $row->description,
            $row->user?->name,
            (string) $row->getAttribute('ip_address'),
            $row->context === null ? null : json_encode($row->context, JSON_THROW_ON_ERROR),
            $row->created_at?->toDateTimeString(),
        ];
    }
}
