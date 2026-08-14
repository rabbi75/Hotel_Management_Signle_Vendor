<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Concerns\Auditable;
use App\Modules\Audit\Exports\ActivityLogExport;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Model change history, recorded by {@see Auditable}.
 */
class ActivityLogController extends AuditController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewActivity', Activity::class);

        return inertia('audit/activity', [
            'table' => $this->table($request)
                ->transform(static fn (Activity $activity): array => [
                    'id' => $activity->getKey(),
                    'log_name' => $activity->log_name,
                    'description' => $activity->description,
                    'event' => $activity->getAttribute('event'),
                    'subject_type' => $activity->subject_type === null ? null : class_basename((string) $activity->subject_type),
                    'subject_id' => $activity->subject_id,
                    'causer' => $activity->causer?->getAttribute('name'),
                    'properties' => $activity->properties->toArray(),
                    'created_at' => $activity->created_at?->toIso8601String(),
                ])
                ->toArray(),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        Gate::authorize('export', Activity::class);

        [$writer, $extension] = $this->writer($request);
        $query = $this->table($request)->exportQuery();

        $this->guardExportSize($query);

        return Excel::download(
            new ActivityLogExport($query),
            $this->filename('activity-log', $extension),
            $writer,
        );
    }

    /**
     * @return TableBuilder<Activity>
     */
    protected function table(Request $request): TableBuilder
    {
        /** @var Builder<Activity> $query */
        $query = Activity::query()->with(['causer', 'subject']);

        return TableBuilder::for($query, $request, 'activity')
            ->columns([
                Column::make('id')->sortable()->hidden(),
                Column::make('description')->searchable()->locked(),
                Column::make('event')->sortable(),
                Column::make('log_name')->sortable(),
                Column::make('subject_type', __('Subject'))->sortable(),
                Column::make('causer')->hidden(),
                Column::make('created_at', __('When'))->sortable(),
            ])
            ->filters([
                Filter::make('event')->options(['Created' => 'created', 'Updated' => 'updated', 'Deleted' => 'deleted', 'Restored' => 'restored']),
                Filter::make('log_name', __('Log')),
                Filter::make('causer_id', __('User'))->type('user'),
                Filter::make('subject_type', __('Subject type')),
                Filter::make('logged', __('Date'))->dateRange()->column('created_at'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
