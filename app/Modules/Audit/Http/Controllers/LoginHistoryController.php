<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Exports\LoginHistoryExport;
use App\Modules\User\Models\LoginHistory;
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
 * Successful and failed authentication attempts.
 */
class LoginHistoryController extends AuditController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewLogins', LoginHistory::class);

        return inertia('audit/logins', [
            'table' => $this->table($request)
                ->transform(static fn (LoginHistory $history): array => [
                    'id' => $history->id,
                    'user' => $history->user?->name,
                    'email' => $history->email,
                    'ip_address' => $history->ip_address,
                    'device_type' => $history->device_type,
                    'platform' => $history->platform,
                    'browser' => $history->browser,
                    'successful' => $history->successful,
                    'failure_reason' => $history->failure_reason,
                    'two_factor_used' => $history->two_factor_used,
                    'logged_in_at' => $history->logged_in_at->toIso8601String(),
                    'logged_out_at' => $history->logged_out_at?->toIso8601String(),
                ])
                ->toArray(),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        Gate::authorize('export', LoginHistory::class);

        [$writer, $extension] = $this->writer($request);
        $query = $this->table($request)->exportQuery();

        $this->guardExportSize($query);

        return Excel::download(
            new LoginHistoryExport($query),
            $this->filename('login-history', $extension),
            $writer,
        );
    }

    /**
     * @return TableBuilder<LoginHistory>
     */
    protected function table(Request $request): TableBuilder
    {
        /** @var Builder<LoginHistory> $query */
        $query = LoginHistory::query()->with('user');

        return TableBuilder::for($query, $request, 'logins')
            ->columns([
                Column::make('email')->searchable()->locked(),
                Column::make('user', __('User'))->searchable('user.name'),
                Column::make('ip_address', __('IP'))->searchable()->sortable(),
                Column::make('device_type', __('Device'))->sortable(),
                Column::make('platform')->sortable(),
                Column::make('browser')->sortable(),
                Column::make('successful', __('Result'))->sortable(),
                Column::make('failure_reason')->hidden(),
                Column::make('two_factor_used')->hidden(),
                Column::make('logged_in_at', __('When'))->sortable(),
                Column::make('logged_out_at')->sortable()->hidden(),
            ])
            ->filters([
                Filter::make('successful', __('Result'))->boolean(),
                Filter::make('user_id', __('User'))->type('user'),
                Filter::make('device_type', __('Device'))->options([
                    'Desktop' => 'desktop', 'Mobile' => 'mobile', 'Tablet' => 'tablet', 'Bot' => 'bot', 'Unknown' => 'unknown',
                ]),
                Filter::make('attempted', __('Date'))->dateRange()->column('logged_in_at'),
            ])
            ->defaultSort('logged_in_at', 'desc');
    }
}
