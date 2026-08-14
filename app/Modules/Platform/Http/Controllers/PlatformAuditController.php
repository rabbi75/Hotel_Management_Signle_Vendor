<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Enums\Severity;
use App\Modules\Audit\Models\SecurityLog;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformAuditController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user('admin')?->cannot('platform.audit.view') ?? true, 403);

        return Inertia::render('admin/audit/index', [
            'table' => $this->table($request)
                ->transform(static fn (SecurityLog $log): array => [
                    'id' => $log->id,
                    'user' => $log->user?->name,
                    'admin' => $log->admin?->name,
                    'company' => $log->company?->name,
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

    /**
     * @return TableBuilder<SecurityLog>
     */
    protected function table(Request $request): TableBuilder
    {
        /** @var Builder<SecurityLog> $query */
        $query = SecurityLog::query()->with(['user:id,name', 'admin:id,name', 'company:id,name']);

        return TableBuilder::for($query, $request, 'platform-audit')
            ->columns([
                Column::make('description')->searchable()->locked(),
                Column::make('event')->sortable(),
                Column::make('severity')->sortable(),
                Column::make('admin', __('Operator'))->searchable('admin.name'),
                Column::make('company', __('Tenant'))->searchable('company.name'),
                Column::make('user', __('User'))->searchable('user.name')->hidden(),
                Column::make('ip_address', __('IP'))->searchable()->hidden(),
                Column::make('created_at', __('When'))->sortable(),
            ])
            ->filters([
                Filter::make('event', __('Event'))->options(
                    collect(SecurityEvent::cases())->mapWithKeys(
                        static fn (SecurityEvent $event): array => [$event->value => $event->label()],
                    )->all(),
                )->multiple(),
                Filter::make('severity', __('Severity'))->options(
                    collect(Severity::cases())->mapWithKeys(
                        static fn (Severity $severity): array => [$severity->value => $severity->label()],
                    )->all(),
                ),
                Filter::make('created_at', __('When'))->dateRange(),
            ])
            ->defaultSort('-created_at');
    }
}
