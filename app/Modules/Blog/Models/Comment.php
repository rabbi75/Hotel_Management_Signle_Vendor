<?php

declare(strict_types=1);

namespace App\Modules\Blog\Models;

use App\Modules\Blog\Enums\CommentStatus;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reader's comment, from either a member or a guest.
 *
 * `body` is stored exactly as typed and is only ever rendered as text — there
 * is no markup path for comments at all, which removes the entire class of
 * problems that comes with letting anonymous strangers author HTML.
 *
 * @property int $id
 * @property int $company_id
 * @property int $post_id
 * @property int|null $parent_id
 * @property int|null $user_id
 * @property string|null $guest_name
 * @property string|null $guest_email
 * @property string $body
 * @property CommentStatus $status
 * @property string|null $ip_address
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read Post|null $post
 */
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use BelongsToCompany, HasFactory;

    protected $table = 'blog_comments';

    protected $fillable = [
        'post_id',
        'parent_id',
        'user_id',
        'guest_name',
        'guest_email',
        'body',
        'status',
        'ip_address',
    ];

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('status', CommentStatus::Approved)
            ->oldest();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', CommentStatus::Approved);
    }

    public function authorName(): string
    {
        if ($this->relationLoaded('user') && $this->user instanceof User) {
            return $this->user->name;
        }

        return $this->guest_name ?? __('Anonymous');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
        ];
    }
}
