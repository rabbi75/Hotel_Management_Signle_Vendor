<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Resources;

use App\Modules\AI\Models\AiPromptTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiPromptTemplate
 */
class AiPromptTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AiPromptTemplate $template */
        $template = $this->resource;

        return [
            'id' => $template->id,
            'name' => $template->name,
            'slug' => $template->slug,
            'description' => $template->description,
            'category' => $template->category,
            'prompt' => $template->prompt,
            'variables' => $template->variables,
            'placeholders' => $template->placeholders(),
            'provider' => $template->provider,
            'model' => $template->model,
            'is_shared' => $template->is_shared,
            'usage_count' => $template->usage_count,
            'author' => $template->relationLoaded('author') ? $template->author?->name : null,
            'created_at' => $template->created_at?->toIso8601String(),
        ];
    }
}
