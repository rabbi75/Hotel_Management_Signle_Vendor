<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers\V1;

use App\Modules\Api\Http\Requests\V1\StoreApiUserRequest;
use App\Modules\Api\Http\Requests\V1\UpdateApiUserRequest;
use App\Modules\Api\Http\Resources\V1\UserResource;
use App\Modules\Company\Enums\CompanyRole;
use App\Modules\User\Enums\UserStatus;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

/**
 * Members of the workspace the presented token is bound to.
 */
class UserController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requireAbility($request, 'read');
        Gate::authorize('viewAny', User::class);

        $query = $this->scoped();

        if (($search = $request->string('search')->toString()) !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $this->collection($request, $query->orderBy('id'), UserResource::class);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $this->requireAbility($request, 'read');

        $user = $this->scoped()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('view', $user);

        return $this->item($request, $user, UserResource::class);
    }

    public function store(StoreApiUserRequest $request): JsonResponse
    {
        $this->requireAbility($request, 'write');
        Gate::authorize('create', User::class);

        $user = new User([
            'first_name' => $request->string('first_name')->toString(),
            'last_name' => $request->string('last_name')->toString(),
            'name' => trim($request->string('first_name').' '.$request->string('last_name')),
            'email' => $request->string('email')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
            'status' => UserStatus::Active,
            'timezone' => (string) config('saas.defaults.timezone'),
            'locale' => (string) config('saas.defaults.locale'),
        ]);

        $user->save();

        $company = current_company();
        $company?->members()->attach($user->id, ['role' => CompanyRole::Member->value, 'joined_at' => now()]);

        return $this->item($request, $user, UserResource::class, 201);
    }

    public function update(UpdateApiUserRequest $request, string $uuid): JsonResponse
    {
        $this->requireAbility($request, 'write');

        $user = $this->scoped()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $user);

        // Absent key means "leave unchanged"; only submitted fields are written.
        $attributes = array_intersect_key(
            [
                'first_name' => $request->string('first_name')->toString(),
                'last_name' => $request->string('last_name')->toString(),
                'job_title' => $request->string('job_title')->toString() ?: null,
                'phone' => $request->string('phone')->toString() ?: null,
            ],
            array_flip(array_keys($request->validated())),
        );

        if (isset($attributes['first_name']) || isset($attributes['last_name'])) {
            $attributes['name'] = trim(($attributes['first_name'] ?? $user->first_name).' '.($attributes['last_name'] ?? $user->last_name));
        }

        $user->fill($attributes)->save();

        return $this->item($request, $user->refresh(), UserResource::class);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $this->requireAbility($request, 'delete');

        $user = $this->scoped()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('delete', $user);

        $user->delete();

        return new JsonResponse(null, 204);
    }

    /**
     * @return Builder<User>
     */
    protected function scoped()
    {
        $companyId = current_company_id();

        return User::query()->whereHas('companies', static fn ($query) => $query->whereKey($companyId));
    }
}
