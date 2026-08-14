<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use App\Modules\AI\Database\Factories\AiPromptTemplateFactory;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $category
 * @property string $prompt
 * @property list<array{name: string, label: string, type: string, required: bool}> $variables
 * @property string|null $provider
 * @property string|null $model
 * @property bool $is_shared
 * @property int $usage_count
 * @property CarbonImmutable|null $created_at
 */
class AiPromptTemplate extends Model
{
    /** @use HasFactory<AiPromptTemplateFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'user_id', 'name', 'slug', 'description', 'category',
        'prompt', 'variables', 'provider', 'model', 'is_shared',
    ];

    /**
     * The `{{variable}}` names the prompt body actually references, which is
     * what the renderer validates against — not the declared schema, which can
     * drift after an edit.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $this->prompt, $matches);

        /** @var list<string> $names */
        $names = array_values(array_unique($matches[1]));

        return $names;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            $template->slug = $template->slug !== '' ? $template->slug : Str::slug($template->name);
        });
    }

    protected static function newFactory(): AiPromptTemplateFactory
    {
        return AiPromptTemplateFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_shared' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
