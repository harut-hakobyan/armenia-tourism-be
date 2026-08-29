<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\TelegramBotClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SendTelegramMessageJob implements ShouldQueue
{
    use Queueable;

    /** @param list<list<array{text: string, callback_data?: string, url?: string}>> $keyboard */
    public function __construct(public string $chatId, public string $text, public array $keyboard = []) {}

    public function handle(TelegramBotClient $client): void
    {
        $client->sendMessage($this->chatId, $this->text, $this->keyboard);
    }
}
