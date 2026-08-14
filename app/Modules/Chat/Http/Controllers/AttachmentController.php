<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Models\MessageAttachment;
use App\Modules\Chat\Services\AttachmentService;
use App\Modules\Chat\Services\MessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(
        protected AttachmentService $attachments,
        protected MessageService $messages,
    ) {}

    /**
     * Attachments are served through the application, never by a public URL:
     * the file is only readable by participants of its conversation.
     */
    public function show(MessageAttachment $attachment): StreamedResponse
    {
        $message = $attachment->message()->firstOrFail();

        Gate::authorize('view', $message);

        $media = $attachment->getFirstMedia(MessageAttachment::COLLECTION);

        abort_if($media === null, 404);

        return $media->toResponse(request());
    }

    public function destroy(MessageAttachment $attachment): RedirectResponse
    {
        /** @var Message $message */
        $message = $attachment->message()->firstOrFail();

        Gate::authorize('delete', $message);

        $this->attachments->delete($attachment);
        $this->messages->broadcastUpdate($message);

        return back()->with('success', __('Attachment removed.'));
    }
}
