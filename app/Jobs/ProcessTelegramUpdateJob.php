<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Telegram\TelegramUpdateHandler;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessTelegramUpdateJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $update */
    public function __construct(public array $update) {}

    public function uniqueId(): string
    {
        return 'telegram-update-'.($this->update['update_id'] ?? hash('sha256', serialize($this->update)));
    }

    public function handle(TelegramUpdateHandler $handler): void
    {
        $handler->handle($this->update);
    }
}
