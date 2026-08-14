<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Resources;

use App\Modules\AI\Models\AiGeneration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiGeneration
 */
class AiGenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AiGeneration $generation */
        $generation = $this->resource;

        return [
            'id' => $generation->id,
            'provider' => $generation->provider,
            'model' => $generation->model,
            'input' => $generation->input,
            'output' => $generation->output,
            'prompt_tokens' => $generation->prompt_tokens,
            'completion_tokens' => $generation->completion_tokens,
            'total_tokens' => $generation->totalTokens(),
            'credits_charged' => $generation->credits_charged,
            'duration_ms' => $generation->duration_ms,
            'status' => $generation->status->value,
            'status_label' => $generation->status->label(),
            'status_color' => $generation->status->color(),
            'stop_reason' => $generation->stop_reason,
            'error' => $generation->error,
            'template_id' => $generation->template_id,
            'template' => $generation->relationLoaded('template') ? $generation->template?->name : null,
            'user' => $generation->relationLoaded('user') ? $generation->user?->name : null,
            'created_at' => $generation->created_at?->toIso8601String(),
        ];
    }
}
