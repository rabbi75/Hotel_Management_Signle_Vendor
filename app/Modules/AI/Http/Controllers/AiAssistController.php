<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Http\Requests\HotelAiAssistRequest;
use App\Modules\AI\Http\Resources\AiGenerationResource;
use App\Modules\AI\Models\AiGeneration;
use App\Modules\AI\Services\HotelAiAssistService;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AiAssistController extends Controller
{
    public function __construct(protected HotelAiAssistService $assists) {}

    public function stream(HotelAiAssistRequest $request): StreamedResponse
    {
        Gate::authorize('create', AiGeneration::class);

        /** @var User $user */
        $user = $request->user();
        /** @var Company $company */
        $company = current_company();

        $action = $request->string('action')->toString();
        $provider = $request->string('provider')->toString() ?: null;

        $payload = [
            'subject_id' => $request->input('subject_id'),
            'hotel_id' => $request->input('hotel_id'),
            'focus' => $request->input('focus'),
            'draft' => $request->input('draft', []),
            'model' => $request->input('model'),
            'max_tokens' => $request->input('max_tokens'),
        ];

        return response()->stream(function () use ($action, $payload, $user, $company, $provider): void {
            $emit = static function (string $event, mixed $data): void {
                echo 'event: '.$event."\n";
                echo 'data: '.json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            try {
                $stream = $this->assists->stream($action, $payload, $user, $company, $provider);

                foreach ($stream as $chunk) {
                    $emit('delta', ['text' => $chunk]);

                    if (connection_aborted() === 1) {
                        return;
                    }
                }

                $generation = $stream->getReturn();
                $emit('done', ['generation' => (new AiGenerationResource($generation))->resolve(request())]);
            } catch (Throwable $exception) {
                $emit('error', ['message' => $exception->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }
}
