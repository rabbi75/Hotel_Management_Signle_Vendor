<?php

declare(strict_types=1);

namespace App\Modules\Search\Providers;

use App\Modules\Company\Models\Company;
use App\Modules\Search\Contracts\SearchProvider;
use App\Modules\Search\Providers\Concerns\SearchesModels;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * The workspaces the searcher belongs to.
 *
 * Not "all workspaces": this is how the palette offers workspace switching, so
 * the result set is exactly the set the user is entitled to enter.
 */
class CompanySearchProvider implements SearchProvider
{
    use SearchesModels;

    public function key(): string
    {
        return 'workspaces';
    }

    public function label(): string
    {
        return (string) __('Workspaces');
    }

    public function icon(): string
    {
        return 'building-2';
    }

    public function permission(): ?string
    {
        return 'companies.view';
    }

    /**
     * @return Collection<int, Company>
     */
    public function search(string $term, int $limit): Collection
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            /** @var Collection<int, Company> $empty */
            $empty = new Collection;

            return $empty;
        }

        $query = $user->companies()->getQuery();

        $indexed = $this->scoutKeys(Company::class, $term, $limit);

        if ($indexed !== null) {
            $query->whereIn('companies.id', $indexed);
        } else {
            $like = $this->likeTerm($term);

            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('companies.name', 'like', $like)
                    ->orWhere('companies.slug', 'like', $like);
            });
        }

        /** @var Collection<int, Company> $results */
        $results = $query->orderBy('companies.name')->limit($limit)->get();

        return $results;
    }

    /**
     * @return array{id: string, title: string, subtitle: string|null, icon: string|null, url: string|null, group: string}
     */
    public function toResult(mixed $model): array
    {
        /** @var Company $model */
        return [
            'id' => 'company-'.$model->id,
            'title' => $model->name,
            'subtitle' => $model->id === current_company_id() ? (string) __('Current workspace') : $model->slug,
            'icon' => $this->icon(),
            'url' => Route::has('companies.show') ? route('companies.show', $model, false) : null,
            'group' => $this->key(),
        ];
    }
}
