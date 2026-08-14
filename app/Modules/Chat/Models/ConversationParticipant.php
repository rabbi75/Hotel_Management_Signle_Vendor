<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Modules\Chat\Enums\ParticipantRole;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property ParticipantRole $role
 * @property CarbonImmutable|null $joined_at
 * @property CarbonImmutable|null $last_read_at
 * @property CarbonImmutable|null $muted_at
 */
class ConversationParticipant extends Pivot
{
    public $incrementing = true;

    protected $table = 'conversation_participants';

    protected $fillable = ['conversation_id', 'user_id', 'role', 'joined_at', 'last_read_at', 'muted_at'];

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isMuted(): bool
    {
        return $this->muted_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ParticipantRole::class,
            'joined_at' => 'immutable_datetime',
            'last_read_at' => 'immutable_datetime',
            'muted_at' => 'immutable_datetime',
        ];
    }
}
