<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel;

/**
 * Shared plumbing for the audit screens: the export format negotiation and the
 * ceiling that stops a filter-less export from trying to stream a year of rows
 * through a synchronous request.
 */
abstract class AuditController extends Controller
{
    /**
     * @return array{0: string, 1: string} The writer type and the file extension.
     */
    protected function writer(Request $request): array
    {
        $format = strtolower((string) $request->query('format', 'csv'));

        return match ($format) {
            'xlsx' => [Excel::XLSX, 'xlsx'],
            'csv' => [Excel::CSV, 'csv'],
            default => throw ValidationException::withMessages([
                'format' => __('Unsupported export format.'),
            ]),
        };
    }

    protected function filename(string $prefix, string $extension): string
    {
        return sprintf('%s-%s.%s', $prefix, now()->format('Y-m-d-His'), $extension);
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    protected function guardExportSize(Builder $query): void
    {
        $max = (int) config('saas.tables.max_sync_export_rows');

        if ($max > 0 && $query->toBase()->getCountForPagination() > $max) {
            throw ValidationException::withMessages([
                'format' => __('This export exceeds :max rows. Narrow the filters and try again.', ['max' => $max]),
            ]);
        }
    }
}
