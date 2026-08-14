<?php

declare(strict_types=1);

namespace App\Modules\Audit\Exports;

use App\Modules\User\Models\LoginHistory;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<LoginHistory>
 */
class LoginHistoryExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Builder<LoginHistory>  $query
     */
    public function __construct(protected Builder $query) {}

    /**
     * @return Builder<LoginHistory>
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
        return ['ID', 'User', 'Email', 'IP address', 'Device', 'Platform', 'Browser', 'Result', 'Failure reason', 'Two factor', 'Logged in at', 'Logged out at'];
    }

    /**
     * @param  LoginHistory  $row
     * @return list<string|int|null>
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->user?->name,
            $row->email,
            $row->ip_address,
            $row->device_type,
            $row->platform,
            $row->browser,
            $row->successful ? 'success' : 'failure',
            $row->failure_reason,
            $row->two_factor_used ? 'yes' : 'no',
            $row->logged_in_at->toDateTimeString(),
            $row->logged_out_at?->toDateTimeString(),
        ];
    }
}
