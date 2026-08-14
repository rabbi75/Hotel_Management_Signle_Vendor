<?php

declare(strict_types=1);

namespace App\Modules\Audit\Exports;

use App\Support\DataTable\TableBuilder;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Spatie\Activitylog\Models\Activity;

/**
 * The query is injected from the controller's {@see TableBuilder}
 * so an export can never contain rows the operator was not looking at.
 *
 * @implements WithMapping<Activity>
 */
class ActivityLogExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Builder<Activity>  $query
     */
    public function __construct(protected Builder $query) {}

    /**
     * @return Builder<Activity>
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
        return ['ID', 'Log', 'Description', 'Event', 'Subject type', 'Subject ID', 'Causer', 'IP address', 'Recorded at'];
    }

    /**
     * @param  Activity  $row
     * @return list<string|int|null>
     */
    public function map($row): array
    {
        return [
            $row->getKey(),
            $row->log_name,
            $row->description,
            (string) $row->getAttribute('event'),
            $row->subject_type === null ? null : class_basename((string) $row->subject_type),
            $row->subject_id,
            $row->causer?->getAttribute('name'),
            (string) $row->properties->get('ip_address', ''),
            $row->created_at?->toDateTimeString(),
        ];
    }
}
