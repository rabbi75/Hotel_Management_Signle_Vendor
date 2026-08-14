<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\Http\Resources\MediaAssetResource;
use App\Modules\Media\Http\Resources\MediaFolderResource;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Models\MediaFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The endpoint other modules embed a media picker against.
 *
 * Same query, same filters and same tenant scoping as the manager screen —
 * a picker that saw a different set of files from the library it claims to
 * show would be a permission bug waiting to happen.
 */
class MediaPickerController extends Controller
{
    use Concerns\BrowsesMedia;

    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', MediaAsset::class);

        $paginator = $this->browse(
            $request,
            $this->currentFolder($request),
            min(60, max(6, (int) $request->integer('per_page', 24))),
        );

        return response()->json([
            'data' => MediaAssetResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'folders' => MediaFolderResource::collection(
                MediaFolder::query()->orderBy('path')->get(),
            )->resolve($request),
        ]);
    }
}
