<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\LoginHistory;
use App\Modules\User\Models\User;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's own sign-in history.
 *
 * Distinct from the workspace-wide history in the Audit module: this one is
 * always constrained to the requesting account and needs no permission, because
 * every user may see their own security events.
 */
class LoginHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);

        $table = TableBuilder::for($this->query($user), $request, 'logins')
            ->columns([
                Column::make('logged_in_at', __('When'))->sortable()->locked(),
                Column::make('ip_address', __('IP address'))->searchable(),
                Column::make('browser', __('Browser'))->searchable(),
                Column::make('platform', __('Platform'))->searchable(),
                Column::make('device_type', __('Device')),
                Column::make('successful', __('Result'))->sortable(),
            ])
            ->filters([
                Filter::make('successful', __('Result'))->boolean(),
                Filter::make('logged_in_at', __('Date'))->dateRange(),
            ])
            ->defaultSort('logged_in_at', 'desc')
            ->transform(static fn (LoginHistory $entry): array => [
                'id' => $entry->id,
                'ip_address' => $entry->ip_address,
                'device_type' => $entry->device_type,
                'platform' => $entry->platform,
                'browser' => $entry->browser,
                'location' => $entry->location,
                'successful' => $entry->successful,
                'failure_reason' => $entry->failure_reason,
                'two_factor_used' => $entry->two_factor_used,
                'logged_in_at' => $entry->logged_in_at->toIso8601String(),
                'logged_in_at_human' => $entry->logged_in_at->diffForHumans(),
                'logged_out_at' => $entry->logged_out_at?->toIso8601String(),
                'is_current' => $entry->session_id !== null
                    && $entry->session_id === request()->session()->getId(),
            ]);

        return Inertia::render('settings/login-history', [
            'table' => $table->toArray(),
            'retention_days' => (int) config('saas.auth.login_history_retention_days'),
        ]);
    }

    /**
     * The one query this controller runs; kept separate so the admin-facing
     * history in the Audit module can reuse it with a different subject.
     *
     * @return Builder<LoginHistory>
     */
    public function query(User $user): Builder
    {
        return LoginHistory::query()->where('user_id', $user->id);
    }

    protected function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
