<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Platform\Http\Resources\AdminResource;
use App\Modules\Platform\Models\Admin;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Manage the operator roster.
 *
 * Super-admin only (`platform.admins.manage`). An admin can never deactivate or
 * demote themselves — the console must not be able to lock its last operator out.
 */
class AdminController extends Controller
{
    public function __construct(protected SecurityLogger $security) {}

    public function index(Request $request): Response
    {
        $this->authorizeManage($request);

        return Inertia::render('admin/admins/index', [
            'table' => $this->table($request)->toArray(),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('admins', 'email')],
            'password' => ['required', 'confirmed', PasswordRule::default()],
            'role' => ['required', Rule::in($this->roleNames())],
        ]);

        $admin = Admin::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'status' => 'active',
        ]);

        $admin->assignRole($validated['role']);

        $this->security->log(
            SecurityEvent::AdminCreated,
            null,
            __('Created admin :email.', ['email' => $admin->email]),
            ['admin_id' => $admin->id, 'role' => $validated['role']],
            $request->user('admin'),
        );

        return back()->with('success', __('Admin created.'));
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('admins', 'email')->ignore($admin->id)],
            'password' => ['nullable', 'confirmed', PasswordRule::default()],
            'role' => ['required', Rule::in($this->roleNames())],
        ]);

        // Guard the last exit: an admin must not strip their own super-admin role
        // and lock the roster.
        if ($this->isSelf($request, $admin) && $validated['role'] !== 'super-admin') {
            return back()->with('error', __('You cannot remove your own super-admin role.'));
        }

        $admin->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (! empty($validated['password'])) {
            $admin->password = $validated['password'];
        }

        $admin->save();
        $admin->syncRoles([$validated['role']]);

        $this->security->log(
            SecurityEvent::AdminUpdated,
            null,
            __('Updated admin :email.', ['email' => $admin->email]),
            ['admin_id' => $admin->id, 'role' => $validated['role']],
            $request->user('admin'),
        );

        return back()->with('success', __('Admin updated.'));
    }

    public function deactivate(Request $request, Admin $admin): RedirectResponse
    {
        $this->authorizeManage($request);

        if ($this->isSelf($request, $admin)) {
            return back()->with('error', __('You cannot deactivate your own account.'));
        }

        $admin->forceFill(['status' => 'suspended'])->save();

        $this->security->log(
            SecurityEvent::AdminDeactivated,
            null,
            __('Deactivated admin :email.', ['email' => $admin->email]),
            ['admin_id' => $admin->id],
            $request->user('admin'),
        );

        return back()->with('success', __('Admin deactivated.'));
    }

    public function reactivate(Request $request, Admin $admin): RedirectResponse
    {
        $this->authorizeManage($request);

        $admin->forceFill(['status' => 'active'])->save();

        $this->security->log(
            SecurityEvent::AdminUpdated,
            null,
            __('Reactivated admin :email.', ['email' => $admin->email]),
            ['admin_id' => $admin->id],
            $request->user('admin'),
        );

        return back()->with('success', __('Admin reactivated.'));
    }

    protected function authorizeManage(Request $request): void
    {
        abort_if($request->user('admin')?->cannot('platform.admins.manage') ?? true, 403);
    }

    protected function isSelf(Request $request, Admin $admin): bool
    {
        return $request->user('admin')?->is($admin) ?? false;
    }

    /**
     * @return TableBuilder<Admin>
     */
    protected function table(Request $request): TableBuilder
    {
        return TableBuilder::for(Admin::query()->with('roles'), $request)
            ->columns([
                Column::make('name')->searchable()->sortable()->locked(),
                Column::make('email')->searchable()->sortable(),
                Column::make('roles', __('Role')),
                Column::make('status')->sortable(),
                Column::make('two_factor_enabled', __('2FA')),
                Column::make('last_login_at', __('Last login'))->sortable(),
            ])
            ->filters([
                Filter::make('status', __('Status'))->options([
                    'active' => __('Active'),
                    'suspended' => __('Suspended'),
                ]),
            ])
            ->defaultSort('created_at')
            ->transform(fn (Admin $admin): array => (new AdminResource($admin))->resolve($request));
    }

    /**
     * @return array<string, string>
     */
    protected function roleOptions(): array
    {
        /** @var array<string, string> $options */
        $options = Role::query()
            ->where('guard_name', 'admin')
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();

        return $options;
    }

    /**
     * @return list<string>
     */
    protected function roleNames(): array
    {
        return array_keys($this->roleOptions());
    }
}
