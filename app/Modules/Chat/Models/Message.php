<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Modules\Chat\Database\Factories\MessageFactory;
use App\Modules\Chat\Enums\MessageType;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property int $conversation_id
 * @property int|null $user_id
 * @property int|null $reply_to_id
 * @property MessageType $type
 * @property string $body
 * @property CarbonImmutable|null $edited_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = ['conversation_id', 'user_id', 'reply_to_id', 'type', 'body', 'edited_at'];

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * @return HasMany<MessageAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /**
     * @return HasMany<MessageReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function isAuthoredBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    /**
     * Whether the sender is still inside the window in which the kit allows an
     * edit. Configured, not hard-coded, so an installation can widen it.
     */
    public function isWithinEditWindow(): bool
    {
        $minutes = max(0, (int) config('saas.chat.edit_window_minutes'));

        return $this->created_at !== null
            && $this->created_at->addMinutes($minutes)->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'edited_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): MessageFactory
    {
        return MessageFactory::new();
    }
}
