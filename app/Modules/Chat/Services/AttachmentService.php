<?php

declare(strict_types=1);

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Models\MessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Stores uploaded files against a message through the media library.
 *
 * The size ceiling is re-checked here as well as in the FormRequest: services
 * are also reachable from jobs and console commands, where no request-level
 * validation ever ran.
 */
class AttachmentService
{
    /**
     * @param  list<UploadedFile>  $files
     * @return list<MessageAttachment>
     */
    public function attach(Message $message, array $files): array
    {
        $attachments = [];

        foreach ($files as $file) {
            $this->guardSize($file);

            $attachment = new MessageAttachment([
                'message_id' => $message->id,
                'name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize() === false ? 0 : $file->getSize(),
            ]);
            $attachment->company_id = $message->company_id;
            $attachment->save();

            $attachment->addMedia($file->getRealPath())
                ->usingFileName($file->hashName())
                ->usingName($attachment->name)
                ->toMediaCollection(MessageAttachment::COLLECTION, (string) config('saas.media.disk'));

            $attachments[] = $attachment;
        }

        return $attachments;
    }

    public function delete(MessageAttachment $attachment): void
    {
        $attachment->clearMediaCollection(MessageAttachment::COLLECTION);
        $attachment->delete();
    }

    /**
     * @throws ValidationException
     */
    protected function guardSize(UploadedFile $file): void
    {
        $maxKb = max(1, (int) config('saas.chat.max_attachment_kb'));
        $size = $file->getSize();

        if ($size !== false && $size > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'attachments' => __('Attachments may not be larger than :max KB.', ['max' => $maxKb]),
            ]);
        }
    }
}
