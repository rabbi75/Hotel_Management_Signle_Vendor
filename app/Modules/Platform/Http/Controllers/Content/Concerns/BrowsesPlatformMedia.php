<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Content\Concerns;

use App\Modules\Media\Enums\MediaType;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Models\MediaFolder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Browse query for platform-owned media, mirroring the tenant library filters.
 */
trait BrowsesPlatformMedia
{
    use ManagesPlatformContent;

    /** @var list<string> */
    protected array $sortable = ['created_at', 'name', 'size'];

    /**
     * @return LengthAwarePaginator<int, MediaAsset>
     */
    protected function browse(Request $request, ?MediaFolder $folder = null, ?int $perPage = null): LengthAwarePaginator
    {
        $query = $this->platformOwned(MediaAsset::class)->with(['folder', 'uploader']);

        $search = $this->searchTerm($request);

        if ($search === null) {
            $query->where('folder_id', $folder?->id);
        }

        $this->applyFilters($query, $request, $search);
        $this->applySort($query, $request);

        return $query
            ->paginate($perPage ?? $this->perPage())
            ->withQueryString();
    }

    /**
     * @param  Builder<MediaAsset>  $query
     */
    protected function applyFilters(Builder $query, Request $request, ?string $search): void
    {
        if ($search !== null) {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('alt', 'like', "%{$search}%");
            });
        }

        $type = MediaType::tryFrom((string) $request->query('type', ''));

        if ($type instanceof MediaType) {
            $query->ofType($type);
        }

        $uploader = $request->query('uploader');

        if (is_numeric($uploader)) {
            $query->where('uploaded_by', (int) $uploader);
        }

        $from = $request->query('from');
        $to = $request->query('to');

        if (is_string($from) && $from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }

        if (is_string($to) && $to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }
    }

    /**
     * @param  Builder<MediaAsset>  $query
     */
    protected function applySort(Builder $query, Request $request): void
    {
        $sort = (string) $request->query('sort', 'created_at');
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $query->orderBy(
            in_array($sort, $this->sortable, true) ? $sort : 'created_at',
            $direction,
        );
    }

    protected function searchTerm(Request $request): ?string
    {
        $search = $request->query('search');

        if (! is_string($search)) {
            return null;
        }

        $search = trim($search);

        return $search === '' ? null : mb_substr($search, 0, 100);
    }

    protected function currentFolder(Request $request): ?MediaFolder
    {
        $id = $request->query('folder');

        if (! is_numeric($id)) {
            return null;
        }

        $folder = $this->platformOwned(MediaFolder::class)->find((int) $id);

        return $folder instanceof MediaFolder ? $folder : null;
    }

    protected function perPage(): int
    {
        return (int) config('saas.media.per_page', 48);
    }

    /**
     * @return array<string, string|null>
     */
    protected function activeFilters(Request $request): array
    {
        return [
            'search' => $this->searchTerm($request),
            'type' => is_string($request->query('type')) && $request->query('type') !== '' ? (string) $request->query('type') : null,
            'uploader' => is_numeric($request->query('uploader')) ? (string) $request->query('uploader') : null,
            'from' => is_string($request->query('from')) && $request->query('from') !== '' ? (string) $request->query('from') : null,
            'to' => is_string($request->query('to')) && $request->query('to') !== '' ? (string) $request->query('to') : null,
            'sort' => (string) $request->query('sort', 'created_at'),
            'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
        ];
    }
}
