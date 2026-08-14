<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\DTOs\ImageEditData;
use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Http\Requests\EditImageRequest;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Services\MediaEditorService;
use App\Modules\User\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Crop, rotate, resize and re-encode.
 *
 * Always writes a new asset: the original is what other records already point
 * at, and an editor that destroys it turns a mis-click into data loss.
 */
class MediaEditorController extends Controller
{
    public function __construct(protected MediaEditorService $editor) {}

    public function __invoke(EditImageRequest $request, MediaAsset $asset): RedirectResponse
    {
        $user = $request->user();

        try {
            $version = $this->editor->apply(
                $asset,
                ImageEditData::fromRequest($request),
                $user instanceof User ? $user : null,
            );
        } catch (MediaException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('Saved as a new version: :name.', ['name' => $version->name]));
    }
}
