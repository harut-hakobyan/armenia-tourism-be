<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

final class ConfigureTelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook {--url= : Public HTTPS backend URL} {--remove : Remove the current webhook}';

    protected $description = 'Configure or remove the Telegram Bot API webhook';

    public function handle(): int
    {
        $token = (string) config('tourism.telegram.bot_token');
        $secret = (string) config('tourism.telegram.webhook_secret');
        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN is not configured.');

            return self::FAILURE;
        }
        if ($this->option('remove')) {
            Http::post("https://api.telegram.org/bot{$token}/deleteWebhook", ['drop_pending_updates' => false])->throw();
            $this->info('Telegram webhook removed.');

            return self::SUCCESS;
        }
        $baseUrl = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        if (! str_starts_with($baseUrl, 'https://') || $secret === '') {
            $this->error('A public HTTPS URL and TELEGRAM_WEBHOOK_SECRET are required.');

            return self::FAILURE;
        }
        Http::post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => "{$baseUrl}/api/v1/telegram/webhook",
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'callback_query'],
            'drop_pending_updates' => false,
        ])->throw();
        $this->info("Telegram webhook configured for {$baseUrl}/api/v1/telegram/webhook");

        return self::SUCCESS;
    }
}
