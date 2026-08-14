<?php

declare(strict_types=1);

namespace App\Modules\Search\Providers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;

/**
 * Chooses between the search index and a LIKE query.
 *
 * Scout is used only for its keys, never for the records themselves: the index
 * has no idea which workspace the searcher is in, so the provider always
 * re-runs a tenant-constrained query against the returned ids. That keeps the
 * index a relevance hint and the database the authority on visibility.
 */
trait SearchesModels
{
    /**
     * Ids the search index matched, or null when the index cannot be trusted to
     * answer: the model is not indexed, or the driver is a local stand-in that
     * offers nothing a LIKE query does not already do.
     *
     * @param  class-string<Model>  $model
     * @return list<int>|null
     */
    protected function scoutKeys(string $model, string $term, int $limit): ?array
    {
        if (! in_array(Searchable::class, class_uses_recursive($model), true)) {
            return null;
        }

        if (in_array((string) config('scout.driver'), ['', 'null', 'collection', 'database'], true)) {
            return null;
        }

        // Built directly rather than through Model::search(), which only exists
        // on the Searchable trait and so cannot be typed on a class-string.
        $builder = app(ScoutBuilder::class, ['model' => new $model, 'query' => $term]);

        return $builder->take($limit)
            ->keys()
            ->map(intval(...))
            ->values()
            ->all();
    }

    /**
     * Escape the characters LIKE treats as wildcards so a term containing `%`
     * matches literally instead of matching everything.
     */
    protected function likeTerm(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
    }
}
