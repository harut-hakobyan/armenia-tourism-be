<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TelegramConnectionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->status($request)]);
    }

    public function link(Request $request): JsonResponse
    {
        abort_if(! $this->configured(), 503, 'Telegram bot is not configured yet. Add the bot credentials and restart the backend.');
        $token = Str::random(32);
        $request->user()->update([
            'telegram_link_token_hash' => hash('sha256', $token),
            'telegram_link_token_expires_at' => now()->addMinutes(15),
        ]);
        $username = ltrim((string) config('tourism.telegram.bot_username'), '@');

        return response()->json(['data' => [
            ...$this->status($request),
            'link_url' => $username === '' ? null : "https://t.me/{$username}?start={$token}",
            'link_code' => $token,
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ]]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $validated = $request->validate(['notifications_enabled' => ['required', 'boolean']]);
        $request->user()->update(['telegram_notifications_enabled' => $validated['notifications_enabled']]);

        return response()->json(['data' => $this->status($request)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->update([
            'telegram_chat_id' => null, 'telegram_username' => null,
            'telegram_link_token_hash' => null, 'telegram_link_token_expires_at' => null,
        ]);

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function status(Request $request): array
    {
        return [
            'connected' => $request->user()->telegram_chat_id !== null,
            'username' => $request->user()->telegram_username,
            'notifications_enabled' => $request->user()->telegram_notifications_enabled,
            'bot_username' => config('tourism.telegram.bot_username'),
            'configured' => $this->configured(),
        ];
    }

    private function configured(): bool
    {
        return filled(config('tourism.telegram.bot_token'))
            && filled(config('tourism.telegram.bot_username'))
            && filled(config('tourism.telegram.webhook_secret'));
    }
}
