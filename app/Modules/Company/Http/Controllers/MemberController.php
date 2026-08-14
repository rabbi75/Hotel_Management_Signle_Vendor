<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Company\Actions\RemoveMember;
use App\Modules\Company\Actions\UpdateMemberRole;
use App\Modules\Company\DTOs\MemberData;
use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Http\Controllers\Concerns\ProvidesPickerOptions;
use App\Modules\Company\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Modules\Company\Http\Requests\UpdateMemberRequest;
use App\Modules\Company\Http\Resources\MemberResource;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class MemberController extends Controller
{
    use ProvidesPickerOptions, ResolvesWorkspace;

    public function index(Request $request): Response
    {
        $company = $this->workspace();

        Gate::authorize('viewMembers', $company);

        $table = TableBuilder::for($this->query($company), $request, 'members')
            ->columns([
                Column::make('name', __('Name'))->sortable()->searchable()->locked(),
                Column::make('email', __('Email'))->sortable()->searchable(),
                Column::make('role', __('Role'))->sortable('company_user.role'),
                Column::make('department', __('Department'))->sortable('departments.name'),
                Column::make('joined_at', __('Joined'))->sortable('company_user.joined_at'),
            ])
            ->filters([
                Filter::make('role', __('Role'))->column('company_user.role')->fromEnum(CompanyRole::class)->multiple(),
                Filter::make('department', __('Department'))
                    ->column('company_user.department_id')
                    ->options($this->departmentOptions()),
            ])
            ->defaultSort('joined_at', 'desc')
            ->transform(fn (User $member): array => (new MemberResource($member))->resolve($request));

        return Inertia::render('companies/members/index', [
            'table' => $table->toArray(),
            'roles' => $this->roleOptions(),
            'departments' => $this->departmentOptions(),
            'permission_roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')->all(),
            'owner_id' => $company->owner_id,
            'can' => [
                'invite' => Gate::allows('inviteMembers', $company),
                'update' => Gate::allows('updateMember', $company),
                'remove' => Gate::allows('removeMember', $company),
            ],
        ]);
    }

    public function update(UpdateMemberRequest $request, User $user, UpdateMemberRole $updateMemberRole): RedirectResponse
    {
        $company = $this->workspace();

        $updateMemberRole->handle($company, $user, MemberData::fromRequest($request), $this->actor($request));

        return back()->with('success', __('Member updated.'));
    }

    public function destroy(Request $request, User $user, RemoveMember $removeMember): RedirectResponse
    {
        $company = $this->workspace();

        Gate::authorize('removeMember', $company);

        $removeMember->handle($company, $user, $this->actor($request));

        return back()->with('success', __('Member removed from the workspace.'));
    }

    /**
     * Membership lives on the pivot, so the listing is a join rather than a
     * relation query: it has to sort and filter on pivot columns.
     *
     * @return Builder<User>
     */
    protected function query(Company $company): Builder
    {
        return User::query()
            ->select('users.*')
            ->addSelect([
                'company_user.role as membership_role',
                'company_user.job_title as membership_job_title',
                'company_user.department_id as membership_department_id',
                'company_user.joined_at as membership_joined_at',
                'departments.name as membership_department_name',
            ])
            ->join('company_user', 'company_user.user_id', '=', 'users.id')
            ->leftJoin('departments', function (JoinClause $join): void {
                $join->on('departments.id', '=', 'company_user.department_id')
                    ->whereNull('departments.deleted_at');
            })
            ->where('company_user.company_id', $company->id)
            ->with('roles');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function roleOptions(): array
    {
        return array_map(
            static fn (CompanyRole $role): array => ['value' => $role->value, 'label' => $role->label()],
            CompanyRole::cases(),
        );
    }
}
