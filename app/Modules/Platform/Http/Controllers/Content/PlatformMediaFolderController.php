<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Models\MediaFolder;
use App\Modules\Media\Services\MediaLibraryService;
use App\Modules\Platform\Http\Controllers\Content\Concerns\ManagesPlatformContent;
use App\Modules\Platform\Http\Requests\Content\StorePlatformMediaFolderRequest;
use App\Modules\Platform\Http\Requests\Content\UpdatePlatformMediaFolderRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformMediaFolderController extends Controller
{
    use ManagesPlatformContent;

    public function __construct(
        protected MediaLibraryService $library,
        protected SecurityLogger $security,
    ) {}

    public function store(StorePlatformMediaFolderRequest $request): RedirectResponse
    {
        try {
            $folder = $this->library->createFolder(
                (string) $request->string('name'),
                $this->folder($request->input('parent_id')),
            );

            if ($folder->getAttribute('company_id') !== null) {
                $folder->company_id = null;
                $folder->save();
            }
        } catch (MediaException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('Folder :name created.', ['name' => $folder->name]));
    }

    public function update(UpdatePlatformMediaFolderRequest $request, MediaFolder $folder): RedirectResponse
    {
        $this->ensurePlatformOwned($folder);

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
        $this->authorizeContentOperator($request);
        $this->ensurePlatformOwned($folder);

        $name = $folder->name;

        try {
            $this->library->deleteFolder($folder);
        } catch (MediaException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->security->log(
            SecurityEvent::MediaFolderDeleted,
            admin: $request->user('admin'),
            description: __('Folder :name deleted.', ['name' => $name]),
            context: ['folder' => $name],
        );

        return back()->with('success', __('Folder deleted.'));
    }

    protected function folder(mixed $id): ?MediaFolder
    {
        if (! is_numeric($id)) {
            return null;
        }

        $folder = MediaFolder::query()->withoutGlobalScopes()->whereNull('company_id')->find((int) $id);

        return $folder instanceof MediaFolder ? $folder : null;
    }
}
