<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Role\Models\Role;
use App\Modules\User\Actions\CreateUser;
use App\Modules\User\Actions\DeleteUser;
use App\Modules\User\Actions\UpdateUser;
use App\Modules\User\DTOs\UserData;
use App\Modules\User\Enums\UserStatus;
use App\Modules\User\Exports\UsersExport;
use App\Modules\User\Http\Requests\StoreUserRequest;
use App\Modules\User\Http\Requests\UpdateUserRequest;
use App\Modules\User\Http\Resources\UserResource;
use App\Modules\User\Models\User;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use App\Support\Enums\Theme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('users/index', [
            'table' => $this->table($request)->toArray(),
            'statuses' => UserStatus::options(),
            'can' => [
                'create' => $request->user()?->can('create', User::class) ?? false,
                'export' => $request->user()?->can('export', User::class) ?? false,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('users/create', $this->formOptions());
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        $user = $createUser->handle(UserData::fromRequest($request));

        return redirect()
            ->route('users.show', $user)
            ->with('success', __('User created.'));
    }

    public function show(Request $request, User $user): Response
    {
        $this->authorize('view', $user);

        $user->load(['roles', 'companies']);

        return Inertia::render('users/show', [
            'user' => (new UserResource($user))->resolve(),
            'login_history' => $user->loginHistories()->limit(10)->get()->map(static fn ($entry): array => [
                'id' => $entry->id,
                'ip_address' => $entry->ip_address,
                'platform' => $entry->platform,
                'browser' => $entry->browser,
                'successful' => $entry->successful,
                'logged_in_at' => $entry->logged_in_at->toIso8601String(),
            ])->all(),
            'can' => [
                'update' => $request->user()?->can('update', $user) ?? false,
                'delete' => $request->user()?->can('delete', $user) ?? false,
                'suspend' => $request->user()?->can('suspend', $user) ?? false,
                'impersonate' => $request->user()?->can('impersonate', $user) ?? false,
            ],
        ]);
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $user->load(['roles', 'companies']);

        return Inertia::render('users/edit', [
            'user' => (new UserResource($user))->resolve(),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        $updateUser->handle($user, UserData::fromRequest($request));

        return redirect()
            ->route('users.show', $user)
            ->with('success', __('User updated.'));
    }

    public function destroy(User $user, DeleteUser $deleteUser): RedirectResponse
    {
        $this->authorize('delete', $user);

        $deleteUser->handle($user);

        return redirect()
            ->route('users.index')
            ->with('success', __('User deleted.'));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('export', User::class);

        $format = in_array($request->query('format'), array_keys(UsersExport::FORMATS), true)
            ? (string) $request->query('format')
            : 'xlsx';

        return Excel::download(
            new UsersExport($this->table($request)->exportQuery()),
            UsersExport::filename($format),
            UsersExport::writerType($format),
        );
    }

    /**
     * @return TableBuilder<User>
     */
    protected function table(Request $request): TableBuilder
    {
        return TableBuilder::for($this->query(), $request)
            ->columns([
                Column::make('name')->searchable()->sortable()->locked(),
                Column::make('email')->searchable()->sortable(),
                Column::make('job_title')->hidden(),
                Column::make('status')->sortable(),
                Column::make('roles'),
                Column::make('last_login_at', __('Last login'))->sortable(),
                Column::make('created_at', __('Registered'))->sortable(),
            ])
            ->filters([
                Filter::make('status')->fromEnum(UserStatus::class)->multiple(),
                Filter::make('role')
                    ->options($this->roleOptions())
                    ->multiple()
                    ->using(static function (Builder $query, mixed $value): void {
                        $query->whereHas(
                            'roles',
                            static fn (Builder $roles) => $roles->whereIn('name', (array) $value),
                        );
                    }),
                Filter::make('created_at', __('Registered'))->dateRange(),
            ])
            ->defaultSort('created_at')
            ->transform(static fn (User $user): array => (new UserResource($user))->resolve());
    }

    /**
     * Users are global records; membership of the active workspace is what
     * makes one visible here, so the pivot — not a company_id column — scopes
     * the listing.
     *
     * @return Builder<User>
     */
    protected function query(): Builder
    {
        return User::query()
            ->with('roles')
            ->whereHas('companies', static fn (Builder $query) => $query->whereKey(current_company_id()));
    }

    /**
     * @return array<string, string>
     */
    protected function roleOptions(): array
    {
        /** @var array<string, string> $options */
        $options = Role::query()->orderBy('name')->pluck('name', 'name')->all();

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'roles' => array_values($this->roleOptions()),
            'statuses' => UserStatus::options(),
            'company_roles' => CompanyRole::options(),
            'themes' => Theme::options(),
            'locales' => config('saas.locales'),
        ];
    }
}
