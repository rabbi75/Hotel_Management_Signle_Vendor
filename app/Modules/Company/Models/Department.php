<?php

declare(strict_types=1);

namespace App\Modules\Company\Models;

use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $parent_id
 * @property int|null $manager_id
 * @property string $name
 * @property string $slug
 */
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = ['parent_id', 'manager_id', 'name', 'slug', 'description'];

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
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $department): void {
            $department->slug = $department->slug ?: Str::slug($department->name);
        });
    }
}
