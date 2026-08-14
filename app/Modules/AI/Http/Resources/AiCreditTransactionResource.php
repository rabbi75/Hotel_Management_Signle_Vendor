<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Resources;

use App\Modules\AI\Models\AiCreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiCreditTransaction
 */
class AiCreditTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AiCreditTransaction $transaction */
        $transaction = $this->resource;

        return [
            'id' => $transaction->id,
            'period' => $transaction->period,
            'type' => $transaction->type->value,
            'type_label' => $transaction->type->label(),
            'type_color' => $transaction->type->color(),
            'credits' => $transaction->credits,
            'description' => $transaction->description,
            'generation_id' => $transaction->generation_id,
            'user' => $transaction->relationLoaded('user') ? $transaction->user?->name : null,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }
}
