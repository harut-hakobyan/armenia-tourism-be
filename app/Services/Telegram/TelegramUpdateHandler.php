<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Actions\Booking\AssignBookingAction;
use App\Contracts\TelegramBotClient;
use App\Enums\BookingStatus;
use App\Enums\DriverTripStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Driver;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use App\Services\Booking\BookingStatusTransitionService;
use App\Services\Booking\DriverTripStatusTransitionService;
use Throwable;

final class TelegramUpdateHandler
{
    public function __construct(
        private readonly TelegramBotClient $client,
        private readonly TelegramBookingNotifier $notifier,
        private readonly BookingStatusTransitionService $bookingStatuses,
        private readonly DriverTripStatusTransitionService $driverStatuses,
        private readonly AvailabilityService $availability,
        private readonly AssignBookingAction $assignments,
    ) {}

    /** @param array<string, mixed> $update */
    public function handle(array $update): void
    {
        if (isset($update['message'])) {
            $this->message($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->callback($update['callback_query']);
        }
    }

    /** @param array<string, mixed> $message */
    private function message(array $message): void
    {
        $chatId = (string) data_get($message, 'chat.id', '');
        if ($chatId === '' || data_get($message, 'chat.type') !== 'private') {
            return;
        }
        $text = trim((string) ($message['text'] ?? ''));
        if (preg_match('/^\/start(?:@\w+)?\s+([A-Za-z0-9]+)$/', $text, $matches)) {
            $user = User::query()->where('telegram_link_token_hash', hash('sha256', $matches[1]))
                ->where('telegram_link_token_expires_at', '>', now())->where('is_active', true)->first();
            if (! $user || ! $user->hasAnyRole(UserRole::Admin, UserRole::Manager, UserRole::Driver)) {
                $this->client->sendMessage($chatId, 'This link is invalid or expired. Generate a new link in your account.');

                return;
            }
            $user->update([
                'telegram_chat_id' => $chatId,
                'telegram_username' => data_get($message, 'from.username'),
                'telegram_link_token_hash' => null,
                'telegram_link_token_expires_at' => null,
            ]);
            $this->client->sendMessage($chatId, '<b>Telegram connected.</b>\nRole: '.e($user->role->value).'\nUse /help to see available actions.');

            return;
        }
        $user = $this->user($chatId);
        if (! $user) {
            $this->client->sendMessage($chatId, 'Connect Telegram from your authenticated Armenia Journeys account first.');

            return;
        }
        if ($text === '/help' || $text === '/start') {
            $commands = $user->role === UserRole::Driver ? '/trips — upcoming assigned trips' : '/bookings — recent bookings';
            $this->client->sendMessage($chatId, "<b>Available commands</b>\n{$commands}\n/help — this message");
        } elseif ($text === '/bookings' && $user->hasAnyRole(UserRole::Admin, UserRole::Manager)) {
            Booking::query()->latest()->limit(5)->get()->each(fn (Booking $booking) => $this->adminBooking($chatId, $booking));
        } elseif ($text === '/trips' && $user->role === UserRole::Driver && $user->driver) {
            Booking::query()->where('driver_id', $user->driver->id)->whereNotIn('booking_status', ['completed', 'cancelled', 'no_show'])
                ->orderBy('starts_at')->limit(5)->get()->each(fn (Booking $booking) => $this->driverBooking($chatId, $booking));
        }
    }

    /** @param array<string, mixed> $callback */
    private function callback(array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $chatId = (string) data_get($callback, 'message.chat.id', '');
        $data = (string) ($callback['data'] ?? '');
        $user = $this->user($chatId);
        if (! $user) {
            $this->client->answerCallback($callbackId, 'Telegram account is not connected.', true);

            return;
        }
        try {
            $parts = explode(':', $data);
            match ($parts[0] ?? '') {
                'bc' => $this->bookingAction($user, $chatId, $parts),
                'ac' => $this->chooseCar($user, $chatId, $parts),
                'ad' => $this->chooseDriver($user, $chatId, $parts),
                'bd' => $this->driverDetails($user, $chatId, (int) ($parts[1] ?? 0)),
                'ds' => $this->driverStatus($user, $chatId, (int) ($parts[1] ?? 0), (string) ($parts[2] ?? '')),
                default => throw new \RuntimeException('Unknown action.'),
            };
            $this->client->answerCallback($callbackId, 'Done');
        } catch (Throwable $exception) {
            report($exception);
            $this->client->answerCallback($callbackId, mb_substr($exception->getMessage(), 0, 180), true);
        }
    }

    /** @param list<string> $parts */
    private function bookingAction(User $user, string $chatId, array $parts): void
    {
        $this->operations($user);
        $booking = Booking::query()->findOrFail((int) ($parts[2] ?? 0));
        match ($parts[1] ?? '') {
            'confirm' => $this->bookingStatuses->transition($booking, BookingStatus::Confirmed, $user, 'Confirmed from Telegram.'),
            'cancel' => $this->bookingStatuses->transition($booking, BookingStatus::Cancelled, $user, 'Cancelled from Telegram.'),
            'detail' => null,
            'assign' => $this->availableCars($chatId, $booking),
            default => throw new \RuntimeException('Unknown booking action.'),
        };
        $this->adminBooking($chatId, $booking->refresh());
    }

    private function availableCars(string $chatId, Booking $booking): void
    {
        $rows = $this->availability->getAvailableCars($booking->starts_at, $booking->planned_end_at, $booking->passengers, $booking->id)
            ->take(10)->map(fn (Car $car) => [['text' => "{$car->brand} {$car->model}", 'callback_data' => "ac:{$booking->id}:{$car->id}"]])->values()->all();
        $this->client->sendMessage($chatId, '<b>Select an available car</b>', $rows);
    }

    /** @param list<string> $parts */
    private function chooseCar(User $user, string $chatId, array $parts): void
    {
        $this->operations($user);
        $booking = Booking::query()->findOrFail((int) ($parts[1] ?? 0));
        $carId = (int) ($parts[2] ?? 0);
        $rows = $this->availability->getAvailableDrivers($booking->starts_at, $booking->planned_end_at, $carId, $booking->id)
            ->take(10)->map(fn (Driver $driver) => [['text' => "{$driver->first_name} {$driver->last_name}", 'callback_data' => "ad:{$booking->id}:{$carId}:{$driver->id}"]])->values()->all();
        $this->client->sendMessage($chatId, '<b>Select an available driver</b>', $rows);
    }

    /** @param list<string> $parts */
    private function chooseDriver(User $user, string $chatId, array $parts): void
    {
        $this->operations($user);
        $booking = Booking::query()->findOrFail((int) ($parts[1] ?? 0));
        $assigned = $this->assignments->execute($booking, Car::query()->findOrFail((int) $parts[2]), Driver::query()->findOrFail((int) $parts[3]), $user, 'Assigned from Telegram.');
        $this->adminBooking($chatId, $assigned);
    }

    private function driverDetails(User $user, string $chatId, int $bookingId): void
    {
        $driver = $this->driver($user);
        $booking = Booking::query()->where('driver_id', $driver->id)->findOrFail($bookingId);
        $this->driverBooking($chatId, $booking);
    }

    private function driverStatus(User $user, string $chatId, int $bookingId, string $status): void
    {
        $driver = $this->driver($user);
        $booking = Booking::query()->where('driver_id', $driver->id)->findOrFail($bookingId);
        $updated = $this->driverStatuses->transition($booking, $driver, DriverTripStatus::from($status), 'Updated from Telegram.');
        $this->driverBooking($chatId, $updated);
    }

    private function adminBooking(string $chatId, Booking $booking): void
    {
        $buttons = match ($booking->booking_status) {
            BookingStatus::Pending => [[['text' => '✅ Confirm', 'callback_data' => "bc:confirm:{$booking->id}"], ['text' => '❌ Cancel', 'callback_data' => "bc:cancel:{$booking->id}"]]],
            BookingStatus::Confirmed => [[['text' => 'Assign car & driver', 'callback_data' => "bc:assign:{$booking->id}"], ['text' => '❌ Cancel', 'callback_data' => "bc:cancel:{$booking->id}"]]],
            default => [],
        };
        $this->client->sendMessage($chatId, $this->notifier->summary($booking->loadMissing(['tour.translations'])), $buttons);
    }

    private function driverBooking(string $chatId, Booking $booking): void
    {
        $next = match ($booking->driver_trip_status) {
            DriverTripStatus::Assigned => DriverTripStatus::OnTheWay,
            DriverTripStatus::OnTheWay => DriverTripStatus::Arrived,
            DriverTripStatus::Arrived => DriverTripStatus::PassengerPickedUp,
            DriverTripStatus::PassengerPickedUp => DriverTripStatus::TripStarted,
            DriverTripStatus::TripStarted => DriverTripStatus::Completed,
            default => null,
        };
        $buttons = $next ? [[['text' => 'Update: '.str_replace('_', ' ', $next->value), 'callback_data' => "ds:{$booking->id}:{$next->value}"]]] : [];
        $this->client->sendMessage($chatId, $this->notifier->summary($booking->loadMissing(['tour.translations'])), $buttons);
    }

    private function user(string $chatId): ?User
    {
        return User::query()->where('telegram_chat_id', $chatId)->where('is_active', true)->first();
    }

    private function operations(User $user): void
    {
        abort_unless($user->hasAnyRole(UserRole::Admin, UserRole::Manager), 403);
    }

    private function driver(User $user): Driver
    {
        abort_unless($user->role === UserRole::Driver && $user->driver, 403);

        return $user->driver;
    }
}
