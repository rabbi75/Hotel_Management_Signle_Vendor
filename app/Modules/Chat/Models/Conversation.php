<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Modules\Chat\Database\Factories\ConversationFactory;
use App\Modules\Chat\Enums\ConversationType;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $created_by
 * @property ConversationType $type
 * @property string|null $name
 * @property string|null $description
 * @property string|null $direct_key
 * @property CarbonImmutable|null $last_message_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = ['created_by', 'type', 'name', 'description', 'direct_key', 'last_message_at'];

    /**
     * The canonical dedup key for a direct conversation between two users.
     *
     * Sorted so that (7, 3) and (3, 7) produce the same string; the unique
     * index on (company_id, direct_key) then makes a duplicate impossible even
     * under concurrent inserts.
     */
    public static function directKeyFor(int $userId, int $otherUserId): string
    {
        $pair = [$userId, $otherUserId];
        sort($pair);

        return implode(':', $pair);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ConversationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * @return BelongsToMany<User, $this, ConversationParticipant>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->using(ConversationParticipant::class)
            ->withPivot(['role', 'joined_at', 'last_read_at', 'muted_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The newest message, for the sidebar preview.
     *
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForParticipant(Builder $query, int $userId): Builder
    {
        return $query->whereHas(
            'participants',
            static fn (Builder $participants): Builder => $participants->where('user_id', $userId),
        );
    }

    public function hasParticipant(int $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    public function participantFor(int $userId): ?ConversationParticipant
    {
        return $this->participants()->where('user_id', $userId)->first();
    }

    /**
     * A display title. Direct conversations have no stored name — the label is
     * whoever the *viewer* is talking to.
     */
    public function titleFor(?int $viewerId = null): string
    {
        if ($this->type !== ConversationType::Direct) {
            return $this->name ?? __('Untitled conversation');
        }

        $users = $this->relationLoaded('users') ? $this->users : $this->users()->get();

        $other = $users->first(static fn (User $user): bool => $user->id !== $viewerId);

        return $other instanceof User ? $other->name : __('Direct message');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'last_message_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): ConversationFactory
    {
        return ConversationFactory::new();
    }
}
