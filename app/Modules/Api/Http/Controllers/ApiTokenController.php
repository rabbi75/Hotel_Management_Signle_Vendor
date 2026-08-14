<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Http\Requests\StoreApiTokenRequest;
use App\Modules\Api\Http\Resources\ApiTokenResource;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Api\Services\ApiTokenService;
use App\Modules\User\Models\User;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    public function __construct(protected ApiTokenService $tokens) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ApiToken::class);

        $table = TableBuilder::for($this->scoped(), $request, 'tokens')
            ->columns([
                Column::make('name', __('Name'))->sortable()->searchable()->locked(),
                Column::make('fingerprint', __('Prefix')),
                Column::make('abilities', __('Abilities')),
                Column::make('last_used_at', __('Last used'))->sortable('last_used_at'),
                Column::make('expires_at', __('Expires'))->sortable('expires_at'),
                Column::make('created_at', __('Created'))->sortable()->hidden(),
            ])
            ->filters([
                Filter::make('expiry', __('Status'))
                    ->options(['Active' => 'active', 'Expired' => 'expired'])
                    ->using(function ($query, mixed $value): void {
                        $value === 'expired'
                            ? $query->whereNotNull('expires_at')->where('expires_at', '<', now())
                            : $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
                    }),
            ])
            ->defaultSort('created_at')
            ->transform(fn (ApiToken $token): array => (new ApiTokenResource($token))->resolve($request));

        return Inertia::render('api/tokens/index', [
            'table' => $table->toArray(),
            'abilities' => ApiTokenService::abilities(),
            'default_expiry_days' => (int) config('saas.api.token_expiry_days'),

            // Read straight off the flash rather than the shared `flash` bag,
            // which only carries the four toast levels. Present on exactly one
            // render — the redirect that follows creation — and never again.
            'created_token' => $request->session()->get('created_token'),
            'can' => [
                'create' => Gate::allows('create', ApiToken::class),
                'revoke' => $request->user() instanceof User && $request->user()->can('api.tokens.revoke'),
            ],
        ]);
    }

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var list<string> $abilities */
        $abilities = array_values(array_map(strval(...), (array) $request->input('abilities', [])));

        $days = $request->input('expires_in_days');

        $result = $this->tokens->create(
            $user,
            (int) current_company_id(),
            $request->string('name')->toString(),
            $abilities,
            is_numeric($days) ? (int) $days : null,
        );

        // The plaintext is flashed exactly once. It is not stored anywhere and
        // cannot be re-derived from the hash, so a missed copy means reissue.
        return back()->with('created_token', [
            'name' => $result['token']->name,
            'plain_text' => $result['plain_text'],
        ]);
    }

    public function destroy(Request $request, ApiToken $token): RedirectResponse
    {
        Gate::authorize('revoke', $token);

        $user = $request->user();
        $this->tokens->revoke($token, $user instanceof User ? $user : null);

        return back()->with('success', __('Token revoked.'));
    }

    /**
     * @return Builder<ApiToken>
     */
    protected function scoped()
    {
        return ApiToken::query()->where('company_id', current_company_id());
    }
}
