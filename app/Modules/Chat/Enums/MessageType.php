<?php

declare(strict_types=1);

namespace App\Modules\Chat\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum MessageType: string
{
    use HasLabel;

    case Text = 'text';
    case System = 'system';
    case Attachment = 'attachment';

    /**
     * System messages are authored by the application, so they are never
     * editable and never carry a sender avatar.
     */
    public function isEditable(): bool
    {
        return $this !== self::System;
    }
}
