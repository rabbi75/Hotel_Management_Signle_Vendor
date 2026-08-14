<?php

declare(strict_types=1);

namespace App\Modules\Company\Models;

use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $department_id
 * @property int|null $lead_id
 * @property string $name
 * @property string $slug
 * @property string|null $color
 */
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = ['department_id', 'lead_id', 'name', 'slug', 'color', 'description'];

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    protected static function booted(): void
    {
        static::creating(function (self $team): void {
            $team->slug = $team->slug ?: Str::slug($team->name);
        });
    }
}
