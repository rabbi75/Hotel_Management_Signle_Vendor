<?php

declare(strict_types=1);

namespace App\Modules\Blog\DTOs;

use App\Modules\Blog\Actions\CreateComment;
use App\Support\DTOs\Data;
use Illuminate\Http\Request;

/**
 * A comment as submitted by a reader.
 *
 * The status is never taken from the request: it is decided by
 * {@see CreateComment} from the workspace's
 * moderation setting, because a client that can choose its own status can
 * choose `approved`.
 */
readonly class CommentData extends Data
{
    public function __construct(
        public string $body,
        public ?int $postId = null,
        public ?int $parentId = null,
        public ?int $userId = null,
        public ?string $guestName = null,
        public ?string $guestEmail = null,
        public ?string $ipAddress = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $user = $request->user();

        return new self(
            body: trim((string) $request->string('body')),
            parentId: $request->filled('parent_id') ? (int) $request->input('parent_id') : null,
            userId: $user?->getAuthIdentifier() === null ? null : (int) $user->getAuthIdentifier(),
            guestName: $user === null ? trim((string) $request->string('guest_name')) : null,
            guestEmail: $user === null ? trim((string) $request->string('guest_email')) : null,
            ipAddress: $request->ip(),
        );
    }
}
