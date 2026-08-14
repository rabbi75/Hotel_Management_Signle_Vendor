<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Resources;

use App\Modules\Notification\Enums\NotificationLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @property-read DatabaseNotification $resource
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource->data;

        $level = NotificationLevel::tryFrom((string) ($data['level'] ?? '')) ?? NotificationLevel::Info;

        return [
            'id' => $this->resource->id,
            'type' => (string) ($data['key'] ?? class_basename($this->resource->type)),
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'icon' => (string) ($data['icon'] ?? $level->icon()),
            'level' => $level->value,
            'action_url' => $data['action_url'] ?? null,
            'action_label' => $data['action_label'] ?? null,
            'read_at' => $this->resource->read_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'created_at_human' => $this->resource->created_at?->diffForHumans(),
        ];
    }
}
