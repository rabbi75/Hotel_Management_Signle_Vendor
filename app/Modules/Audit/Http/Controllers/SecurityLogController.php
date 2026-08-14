<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Enums\Severity;
use App\Modules\Audit\Exports\SecurityLogExport;
use App\Modules\Audit\Models\SecurityLog;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Security-relevant events that are not ordinary model changes.
 */
class SecurityLogController extends AuditController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewSecurity', SecurityLog::class);

        return inertia('audit/security', [
            'table' => $this->table($request)
                ->transform(static fn (SecurityLog $log): array => [
                    'id' => $log->id,
                    'user' => $log->user?->name,
                    'event' => $log->event->value,
                    'event_label' => $log->event->label(),
                    'severity' => $log->severity->value,
                    'severity_color' => $log->severity->color(),
                    'description' => $log->description,
                    'context' => $log->context,
                    'ip_address' => $log->getAttribute('ip_address'),
                    'created_at' => $log->created_at?->toIso8601String(),
                ])
                ->toArray(),
            'events' => SecurityEvent::options(),
            'severities' => Severity::options(),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        Gate::authorize('export', SecurityLog::class);

        [$writer, $extension] = $this->writer($request);
        $query = $this->table($request)->exportQuery();

        $this->guardExportSize($query);

        return Excel::download(
            new SecurityLogExport($query),
            $this->filename('security-log', $extension),
            $writer,
        );
    }

    /**
     * @return TableBuilder<SecurityLog>
     */
    protected function table(Request $request): TableBuilder
    {
        /** @var Builder<SecurityLog> $query */
        $query = SecurityLog::query()->with('user');

        return TableBuilder::for($query, $request, 'security')
            ->columns([
                Column::make('description')->searchable()->locked(),
                Column::make('event')->sortable(),
                Column::make('severity')->sortable(),
                Column::make('user', __('User'))->searchable('user.name'),
                Column::make('ip_address', __('IP'))->searchable()->hidden(),
                Column::make('created_at', __('When'))->sortable(),
            ])
            ->filters([
                Filter::make('event')->fromEnum(SecurityEvent::class)->multiple(),
                Filter::make('severity')->fromEnum(Severity::class)->multiple(),
                Filter::make('user_id', __('User'))->type('user'),
                Filter::make('logged', __('Date'))->dateRange()->column('created_at'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
