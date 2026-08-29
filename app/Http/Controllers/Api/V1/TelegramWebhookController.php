<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTelegramUpdateJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configured = (string) config('tourism.telegram.webhook_secret');
        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');
        abort_if($configured === '' || ! hash_equals($configured, $provided), 403);
        $update = $request->validate(['update_id' => ['required', 'integer']]) + $request->all();
        ProcessTelegramUpdateJob::dispatch($update);

        return response()->json(['ok' => true]);
    }
}
