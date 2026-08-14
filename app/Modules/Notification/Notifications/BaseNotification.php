<?php

declare(strict_types=1);

namespace App\Modules\Notification\Notifications;

use App\Modules\Notification\Enums\NotificationChannel;
use App\Modules\Notification\Enums\NotificationLevel;
use App\Modules\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The base every notification in the application extends.
 *
 * Subclasses describe *what* to say — title, body, level, optional call to
 * action — and inherit the delivery rules: which channels are enabled for the
 * installation, which of those the recipient has opted out of, and the exact
 * payload shape the bell and the React toast expect.
 */
abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return array_values(array_map(
            static fn (NotificationChannel $channel): string => $channel->value,
            array_filter(
                NotificationChannel::enabled(),
                fn (NotificationChannel $channel): bool => $this->wants($notifiable, $channel),
            ),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'key' => $this->key(),
            'title' => $this->title($notifiable),
            'body' => $this->body($notifiable),
            'icon' => $this->icon(),
            'level' => $this->level()->value,
            'action_url' => $this->actionUrl($notifiable),
            'action_label' => $this->actionLabel(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->level($this->level()->mailTheme())
            ->subject($this->title($notifiable))
            ->line($this->body($notifiable));

        $url = $this->actionUrl($notifiable);

        if ($url !== null) {
            $message->action($this->actionLabel() ?? __('View'), $url);
        }

        return $message;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toDatabase($notifiable)))
            ->onQueue('broadcasts');
    }

    /**
     * Stable identifier used for per-type preferences and for grouping in the
     * UI. Derived from the class name so it survives a namespace move.
     */
    public function key(): string
    {
        return Str::snake(class_basename($this));
    }

    public function level(): NotificationLevel
    {
        return NotificationLevel::Info;
    }

    public function icon(): string
    {
        return $this->level()->icon();
    }

    public function actionUrl(object $notifiable): ?string
    {
        return null;
    }

    public function actionLabel(): ?string
    {
        return null;
    }

    abstract public function title(object $notifiable): string;

    abstract public function body(object $notifiable): string;

    /**
     * A channel is used when the installation enables it and the recipient has
     * not switched it off — globally or for this notification type.
     */
    protected function wants(object $notifiable, NotificationChannel $channel): bool
    {
        if (! $channel->isOptional()) {
            return true;
        }

        $preferences = $notifiable instanceof User ? $notifiable->preferences : null;

        if (! is_array($preferences)) {
            return true;
        }

        $perType = Arr::get($preferences, "notifications.types.{$this->key()}.{$channel->value}");

        if (is_bool($perType)) {
            return $perType;
        }

        return (bool) Arr::get($preferences, $channel->preferenceKey(), true);
    }
}
