<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Http\Requests\StoreMediaFolderRequest;
use App\Modules\Media\Http\Requests\UpdateMediaFolderRequest;
use App\Modules\Media\Models\MediaFolder;
use App\Modules\Media\Services\MediaLibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MediaFolderController extends Controller
{
    public function __construct(
        protected MediaLibraryService $library,
        protected SecurityLogger $security,
    ) {}

    public function store(StoreMediaFolderRequest $request): RedirectResponse
    {
        try {
            $folder = $this->library->createFolder(
                (string) $request->string('name'),
                $this->folder($request->input('parent_id')),
            );
        } catch (MediaException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('Folder :name created.', ['name' => $folder->name]));
    }

    public function update(UpdateMediaFolderRequest $request, MediaFolder $folder): RedirectResponse
    {
        try {
            if ($request->has('name')) {
                $this->library->renameFolder($folder, (string) $request->string('name'));
            }

            if ($request->has('parent_id')) {
                $this->library->moveFolder($folder, $this->folder($request->input('parent_id')));
            }
        } catch (MediaException $exception) {
            return back()->withErrors(['parent_id' => $exception->getMessage()]);
        }

        return back()->with('success', __('Folder updated.'));
    }

    public function destroy(Request $request, MediaFolder $folder): RedirectResponse
    {
        Gate::authorize('delete', $folder);

        $name = $folder->name;

        try {
            $this->library->deleteFolder($folder);
        } catch (MediaException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->security->log(
            SecurityEvent::MediaFolderDeleted,
            $request->user(),
            __('Folder :name deleted.', ['name' => $name]),
            ['folder' => $name],
        );

        return back()->with('success', __('Folder deleted.'));
    }

    protected function folder(mixed $id): ?MediaFolder
    {
        return is_numeric($id) ? MediaFolder::query()->find((int) $id) : null;
    }
}
