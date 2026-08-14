<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Http\Resources\ApiRequestLogResource;
use App\Modules\Api\Models\ApiRequestLog;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApiLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ApiRequestLog::class);

        $table = TableBuilder::for(ApiRequestLog::query()->with(['user', 'token']), $request, 'logs')
            ->columns([
                Column::make('method', __('Method'))->locked(),
                Column::make('path', __('Path'))->searchable()->locked(),
                Column::make('status', __('Status'))->sortable()->align('right'),
                Column::make('duration_ms', __('Duration'))->sortable()->align('right'),
                Column::make('user', __('User')),
                Column::make('token', __('Token')),
                Column::make('ip_address', __('IP'))->hidden(),
                Column::make('created_at', __('When'))->sortable(),
            ])
            ->filters([
                Filter::make('method', __('Method'))->options(['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH', 'DELETE' => 'DELETE']),
                Filter::make('outcome', __('Outcome'))
                    ->options(['Success' => 'success', 'Client error' => 'client_error', 'Server error' => 'server_error'])
                    ->using(function ($query, mixed $value): void {
                        match ($value) {
                            'success' => $query->where('status', '<', 400),
                            'client_error' => $query->whereBetween('status', [400, 499]),
                            'server_error' => $query->where('status', '>=', 500),
                            default => null,
                        };
                    }),
                Filter::make('created_at', __('Date'))->dateRange(),
            ])
            ->defaultSort('created_at')
            ->transform(fn (ApiRequestLog $log): array => (new ApiRequestLogResource($log))->resolve($request));

        return Inertia::render('api/logs/index', [
            'table' => $table->toArray(),
            'retention_days' => (int) config('saas.api.log_retention_days'),
            'bodies_logged' => (bool) config('saas.api.log_bodies'),
        ]);
    }

    public function show(Request $request, ApiRequestLog $log): JsonResponse
    {
        Gate::authorize('view', $log);

        return new JsonResponse([
            'log' => (new ApiRequestLogResource($log->load(['user', 'token'])))->resolve($request),
        ]);
    }
}
