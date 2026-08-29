<?php

declare(strict_types=1);

namespace App\Contracts;

interface TelegramBotClient
{
    /** @param list<list<array{text: string, callback_data?: string, url?: string}>> $keyboard */
    public function sendMessage(string $chatId, string $text, array $keyboard = []): void;

    public function answerCallback(string $callbackId, string $text = '', bool $alert = false): void;
}
