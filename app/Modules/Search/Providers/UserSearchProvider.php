<?php

declare(strict_types=1);

namespace App\Modules\Search\Providers;

use App\Modules\Search\Contracts\SearchProvider;
use App\Modules\Search\Providers\Concerns\SearchesModels;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * People in the active workspace.
 *
 * Membership is the tenancy boundary here: `users` has no company_id, so the
 * query joins company_user rather than relying on a global scope that does not
 * apply to this model.
 */
class UserSearchProvider implements SearchProvider
{
    use SearchesModels;

    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return (string) __('Users');
    }

    public function icon(): string
    {
        return 'user-round';
    }

    public function permission(): ?string
    {
        return 'users.view';
    }

    /**
     * @return Collection<int, User>
     */
    public function search(string $term, int $limit): Collection
    {
        $companyId = current_company_id();

        if ($companyId === null) {
            /** @var Collection<int, User> $empty */
            $empty = new Collection;

            return $empty;
        }

        $query = User::query()
            ->select('users.*')
            ->join('company_user', 'company_user.user_id', '=', 'users.id')
            ->where('company_user.company_id', $companyId);

        $indexed = $this->scoutKeys(User::class, $term, $limit);

        if ($indexed !== null) {
            $query->whereIn('users.id', $indexed);
        } else {
            $like = $this->likeTerm($term);

            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('users.name', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhere('users.job_title', 'like', $like);
            });
        }

        /** @var Collection<int, User> $results */
        $results = $query->orderBy('users.name')->limit($limit)->get();

        return $results;
    }

    /**
     * @return array{id: string, title: string, subtitle: string|null, icon: string|null, url: string|null, group: string}
     */
    public function toResult(mixed $model): array
    {
        /** @var User $model */
        return [
            'id' => 'user-'.$model->id,
            'title' => $model->name,
            'subtitle' => $model->job_title ?? $model->email,
            'icon' => $this->icon(),
            'url' => Route::has('users.show') ? route('users.show', $model, false) : null,
            'group' => $this->key(),
        ];
    }
}
