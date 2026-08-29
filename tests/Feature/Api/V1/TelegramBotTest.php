<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Contracts\TelegramBotClient;
use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessTelegramUpdateJob;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Driver;
use App\Models\Tour;
use App\Models\User;
use App\Services\Telegram\TelegramUpdateHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TelegramBotTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_link_telegram_and_confirm_booking(): void
    {
        config([
            'tourism.telegram.bot_username' => 'ArmeniaJourneysBot',
            'tourism.telegram.bot_token' => 'test-token',
            'tourism.telegram.webhook_secret' => 'test-secret',
        ]);
        $client = new RecordingTelegramClient;
        $this->app->instance(TelegramBotClient::class, $client);
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $link = $this->actingAs($manager)->postJson('/api/v1/telegram/link')
            ->assertOk()->assertJsonPath('data.connected', false);
        $code = (string) $link->json('data.link_code');
        $this->app->make(TelegramUpdateHandler::class)->handle([
            'message' => ['text' => $code, 'chat' => ['id' => 123456, 'type' => 'private'], 'from' => ['username' => 'manager_one']],
        ]);
        $this->assertDatabaseHas('users', ['id' => $manager->id, 'telegram_chat_id' => '123456', 'telegram_username' => 'manager_one']);
        $this->assertNotEmpty($client->messages[0]['keyboard']);

        $this->seed();
        $booking = $this->createBooking();
        $this->app->make(TelegramUpdateHandler::class)->handle([
            'message' => ['text' => '/bookings@ArmeniaJourneysBot', 'chat' => ['id' => 123456, 'type' => 'private']],
        ]);
        $this->assertStringContainsString($booking->booking_number, $client->messages[array_key_last($client->messages)]['text']);
        $this->app->make(TelegramUpdateHandler::class)->handle([
            'callback_query' => ['id' => 'callback-1', 'data' => "bc:confirm:{$booking->id}", 'message' => ['chat' => ['id' => 123456]]],
        ]);

        $this->assertSame(BookingStatus::Confirmed, $booking->refresh()->booking_status);
        $this->assertNotEmpty($client->messages);
        $this->assertSame('Done', $client->answers['callback-1']);
        $confirmedMessage = $client->messages[array_key_last($client->messages)];
        $buttonLabels = collect($confirmedMessage['keyboard'])->flatten(1)->pluck('text');
        $this->assertTrue($buttonLabels->contains('Assign car & driver'));
        $this->assertFalse($buttonLabels->contains('No show'));

        $this->app->make(TelegramUpdateHandler::class)->handle([
            'callback_query' => ['id' => 'callback-assign', 'data' => "bc:assign:{$booking->id}", 'message' => ['chat' => ['id' => 123456]]],
        ]);
        $vehiclePicker = $client->messages[array_key_last($client->messages)];
        $this->assertStringContainsString('Step 1 of 2', $vehiclePicker['text']);
        $driverCarId = Driver::query()->where('active', true)->firstOrFail()->cars()->firstOrFail()->id;
        $carCallback = collect($vehiclePicker['keyboard'])->flatten(1)->pluck('callback_data')
            ->first(fn ($data) => str_ends_with((string) $data, ":{$driverCarId}"));
        $this->assertNotNull($carCallback);
        $this->app->make(TelegramUpdateHandler::class)->handle([
            'callback_query' => ['id' => 'callback-car', 'data' => $carCallback, 'message' => ['chat' => ['id' => 123456]]],
        ]);
        $this->assertStringContainsString('Step 2 of 2', $client->messages[array_key_last($client->messages)]['text']);

        $this->app->make(TelegramUpdateHandler::class)->handle([
            'message' => ['text' => '/menu', 'chat' => ['id' => 123456, 'type' => 'private']],
        ]);
        $this->assertStringNotContainsString('/bookings', $client->messages[array_key_last($client->messages)]['text']);
        $this->app->make(TelegramUpdateHandler::class)->handle([
            'message' => ['text' => '/help', 'chat' => ['id' => 123456, 'type' => 'private']],
        ]);
        $this->assertStringContainsString('/bookings', $client->messages[array_key_last($client->messages)]['text']);

        $this->actingAs($manager)->patchJson('/api/v1/telegram/preferences', ['notifications_enabled' => false])
            ->assertOk()->assertJsonPath('data.notifications_enabled', false);

        $this->app->make(TelegramUpdateHandler::class)->handle([
            'message' => ['text' => '/notification on', 'chat' => ['id' => 123456, 'type' => 'private']],
        ]);
        $this->assertTrue($manager->refresh()->telegram_notifications_enabled);

        $this->app->make(TelegramUpdateHandler::class)->handle([
            'callback_query' => ['id' => 'callback-2', 'data' => 'nt:off', 'message' => ['chat' => ['id' => 123456]]],
        ]);
        $this->assertFalse($manager->refresh()->telegram_notifications_enabled);
        $this->assertSame('Done', $client->answers['callback-2']);
    }

    public function test_connection_link_requires_complete_bot_configuration(): void
    {
        config([
            'tourism.telegram.bot_username' => null,
            'tourism.telegram.bot_token' => null,
            'tourism.telegram.webhook_secret' => null,
        ]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)->getJson('/api/v1/telegram')
            ->assertOk()->assertJsonPath('data.configured', false);
        $this->actingAs($manager)->postJson('/api/v1/telegram/link')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Telegram bot is not configured yet. Add the bot credentials and restart the backend.');
    }

    public function test_webhook_requires_secret_and_queues_update_once(): void
    {
        Queue::fake();
        config(['tourism.telegram.webhook_secret' => 'test-webhook-secret']);
        $payload = ['update_id' => 9988, 'message' => ['text' => '/help']];
        $this->postJson('/api/v1/telegram/webhook', $payload)->assertForbidden();
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret')
            ->postJson('/api/v1/telegram/webhook', $payload)->assertOk()->assertJsonPath('ok', true);
        Queue::assertPushed(ProcessTelegramUpdateJob::class, fn (ProcessTelegramUpdateJob $job) => $job->uniqueId() === 'telegram-update-9988');
    }

    private function createBooking(): Booking
    {
        $tour = Tour::query()->where('slug', 'garni-geghard')->firstOrFail();
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $response = $this->postJson('/api/v1/bookings', [
            'idempotency_key' => (string) Str::uuid(), 'service_type' => 'tour',
            'tour_id' => $tour->id, 'car_id' => $car->id,
            'booking_date' => now()->addDays(30)->toDateString(), 'pickup_time' => '09:00',
            'passengers' => 1, 'pickup_address' => 'Republic Square, Yerevan',
            'customer_name' => 'Telegram Guest', 'customer_phone' => '+37499123456',
            'payment_method' => 'pay_driver',
        ])->assertCreated();

        return Booking::query()->where('booking_number', $response->json('data.booking_number'))->firstOrFail();
    }
}

final class RecordingTelegramClient implements TelegramBotClient
{
    /** @var list<array{chat_id: string, text: string, keyboard: array}> */
    public array $messages = [];

    /** @var array<string, string> */
    public array $answers = [];

    public function sendMessage(string $chatId, string $text, array $keyboard = []): void
    {
        $this->messages[] = ['chat_id' => $chatId, 'text' => $text, 'keyboard' => $keyboard];
    }

    public function answerCallback(string $callbackId, string $text = '', bool $alert = false): void
    {
        $this->answers[$callbackId] = $text;
    }
}
