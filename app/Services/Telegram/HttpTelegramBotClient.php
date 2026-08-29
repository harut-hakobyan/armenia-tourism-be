<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\TelegramBotClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HttpTelegramBotClient implements TelegramBotClient
{
    public function sendMessage(string $chatId, string $text, array $keyboard = []): void
    {
        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
        if ($keyboard !== []) {
            $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
        }
        $this->post('sendMessage', $payload);
    }

    public function answerCallback(string $callbackId, string $text = '', bool $alert = false): void
    {
        $this->post('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text, 'show_alert' => $alert]);
    }

    /** @param array<string, mixed> $payload */
    private function post(string $method, array $payload): void
    {
        $token = (string) config('tourism.telegram.bot_token');
        if ($token === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not configured.');
        }
        Http::timeout(10)->retry(2, 250)->post("https://api.telegram.org/bot{$token}/{$method}", $payload)->throw();
    }
}
