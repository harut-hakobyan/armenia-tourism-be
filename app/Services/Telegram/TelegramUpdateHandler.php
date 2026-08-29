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
        if (preg_match('/^(?:\/start(?:@\w+)?\s+)?([A-Za-z0-9]{32})$/', $text, $matches)) {
            $user = User::query()->where('telegram_link_token_hash', hash('sha256', $matches[1]))
                ->where('telegram_link_token_expires_at', '>', now('UTC'))->where('is_active', true)->first();
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
            $this->client->sendMessage(
                $chatId,
                "<b>Telegram connected.</b>\nRole: ".e($user->role->value)."\nChoose an action below.",
                $this->mainMenu($user),
            );

            return;
        }
        $user = $this->user($chatId);
        if (! $user) {
            $this->client->sendMessage($chatId, 'Open Telegram from your Armenia Journeys account and press the Start button. A plain /start command cannot connect an account.');

            return;
        }
        if (preg_match('/^\/help(?:@\w+)?$/i', $text)) {
            $this->showHelp($user, $chatId);
        } elseif (preg_match('/^\/(?:start|menu)(?:@\w+)?$/i', $text)) {
            $this->showMenu($user, $chatId);
        } elseif (preg_match('/^\/(?:notifications?|alerts?)(?:@\w+)?\s+(on|off)$/i', $text, $matches)) {
            $enabled = strtolower($matches[1]) === 'on';
            $this->setNotifications($user, $chatId, $enabled);
        } elseif (preg_match('/^\/bookings(?:@\w+)?$/i', $text) && $user->hasAnyRole(UserRole::Admin, UserRole::Manager)) {
            $this->adminBookings($user, $chatId, 'recent');
        } elseif (preg_match('/^\/trips(?:@\w+)?$/i', $text) && $user->role === UserRole::Driver) {
            $this->driverTrips($user, $chatId);
        } else {
            $this->client->sendMessage($chatId, 'Unknown or unauthorized command. Choose an available action.', $this->mainMenu($user));
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
                'menu' => $this->menuAction($user, $chatId, (string) ($parts[1] ?? 'help')),
                'nt' => $this->notificationAction($user, $chatId, (string) ($parts[1] ?? '')),
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
        if (($parts[1] ?? '') === 'assign') {
            $this->availableCars($chatId, $booking);

            return;
        }
        match ($parts[1] ?? '') {
            'confirm' => $this->bookingStatuses->transition($booking, BookingStatus::Confirmed, $user, 'Confirmed from Telegram.'),
            'cancel' => $this->bookingStatuses->transition($booking, BookingStatus::Cancelled, $user, 'Cancelled from Telegram.'),
            'detail' => null,
            default => throw new \RuntimeException('Unknown booking action.'),
        };
        $this->adminBooking($chatId, $booking->refresh());
    }

    private function availableCars(string $chatId, Booking $booking): void
    {
        $cars = $this->availability->getAvailableCars($booking->starts_at, $booking->planned_end_at, $booking->passengers, $booking->id)
            ->take(10)->values();
        if ($cars->isEmpty()) {
            $this->client->sendMessage($chatId, '<b>No vehicles are available</b> for this booking time.', [
                [['text' => 'Back to booking', 'callback_data' => "bc:detail:{$booking->id}"]],
            ]);

            return;
        }
        $details = $cars->map(fn (Car $car, int $index): string => ($index + 1).'. <b>'.e("{$car->brand} {$car->model}").'</b>'
            .' · '.e($car->plate_number)."\n   ".e(ucfirst($car->category->value)).' · '.e((string) $car->passenger_capacity).' passengers')->implode("\n\n");
        $rows = $cars->map(fn (Car $car, int $index): array => [[
            'text' => ($index + 1).". {$car->brand} {$car->model}",
            'callback_data' => "ac:{$booking->id}:{$car->id}",
        ]])->all();
        $rows[] = [['text' => 'Back to booking', 'callback_data' => "bc:detail:{$booking->id}"]];
        $this->client->sendMessage(
            $chatId,
            "<b>Assign car &amp; driver</b>\nBooking: <b>".e($booking->booking_number)."</b>\n\n<b>Step 1 of 2 · Choose a vehicle</b>\n\n{$details}",
            $rows,
        );
    }

    /** @param list<string> $parts */
    private function chooseCar(User $user, string $chatId, array $parts): void
    {
        $this->operations($user);
        $booking = Booking::query()->findOrFail((int) ($parts[1] ?? 0));
        $carId = (int) ($parts[2] ?? 0);
        $car = Car::query()->findOrFail($carId);
        $drivers = $this->availability->getAvailableDrivers($booking->starts_at, $booking->planned_end_at, $carId, $booking->id)
            ->take(10)->values();
        if ($drivers->isEmpty()) {
            $this->client->sendMessage(
                $chatId,
                '<b>No authorized drivers are available</b> for '.e("{$car->brand} {$car->model}").'.',
                [[['text' => 'Choose another vehicle', 'callback_data' => "bc:assign:{$booking->id}"]]],
            );

            return;
        }
        $details = $drivers->map(function (Driver $driver, int $index): string {
            $languages = implode(', ', array_map('strtoupper', $driver->languages ?? []));
            $rating = $driver->rating === null ? 'Not rated' : 'Rating '.number_format((float) $driver->rating, 1).'/5';

            return ($index + 1).'. <b>'.e("{$driver->first_name} {$driver->last_name}")."</b>\n   ".e($rating)
                .($languages === '' ? '' : ' · '.e($languages));
        })->implode("\n\n");
        $rows = $drivers->map(fn (Driver $driver, int $index): array => [[
            'text' => ($index + 1).". {$driver->first_name} {$driver->last_name}",
            'callback_data' => "ad:{$booking->id}:{$carId}:{$driver->id}",
        ]])->all();
        $rows[] = [['text' => 'Choose another vehicle', 'callback_data' => "bc:assign:{$booking->id}"]];
        $this->client->sendMessage(
            $chatId,
            "<b>Assign car &amp; driver</b>\nBooking: <b>".e($booking->booking_number)."</b>\nVehicle: <b>".e("{$car->brand} {$car->model}")."</b>\n\n<b>Step 2 of 2 · Choose a driver</b>\n\n{$details}",
            $rows,
        );
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
        $updated = $this->driverStatuses->transition($booking, $driver, DriverTripStatus::from($status), 'Updated from Telegram.');
        $this->driverBooking($chatId, $updated);
    }

    private function adminBooking(string $chatId, Booking $booking): void
    {
        $buttons = match ($booking->booking_status) {
            BookingStatus::Pending => [
                [['text' => 'Confirm', 'callback_data' => "bc:confirm:{$booking->id}"], ['text' => 'Cancel', 'callback_data' => "bc:cancel:{$booking->id}"]],
            ],
            BookingStatus::Confirmed => [
                [['text' => 'Assign car & driver', 'callback_data' => "bc:assign:{$booking->id}"]],
                [['text' => 'Cancel', 'callback_data' => "bc:cancel:{$booking->id}"]],
            ],
            BookingStatus::Assigned => [
                [['text' => 'Cancel', 'callback_data' => "bc:cancel:{$booking->id}"]],
            ],
            BookingStatus::DriverOnTheWay => [
                [['text' => 'Cancel', 'callback_data' => "bc:cancel:{$booking->id}"]],
            ],
            default => [],
        };
        $buttons[] = [
            ['text' => 'Refresh details', 'callback_data' => "bc:detail:{$booking->id}"],
            ['text' => 'Main menu', 'callback_data' => 'menu:home'],
        ];
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
        $buttons[] = [['text' => 'Main menu', 'callback_data' => 'menu:home']];
        $this->client->sendMessage($chatId, $this->notifier->summary($booking->loadMissing(['tour.translations'])), $buttons);
    }

    private function showMenu(User $user, string $chatId): void
    {
        $this->client->sendMessage(
            $chatId,
            '<b>Available actions</b> · choose an option below.',
            $this->mainMenu($user),
        );
    }

    private function showHelp(User $user, string $chatId): void
    {
        $primaryCommand = $user->role === UserRole::Driver
            ? '/trips - upcoming assigned trips'
            : '/bookings - recent bookings';
        $this->client->sendMessage(
            $chatId,
            "<b>Manual commands</b>\n{$primaryCommand}\n/notification on - enable alerts\n/notification off - pause alerts\n/menu - show action buttons\n/help - show this guide",
            $this->mainMenu($user),
        );
    }

    /** @return list<list<array{text: string, callback_data: string}>> */
    private function mainMenu(User $user): array
    {
        $notificationButton = $user->telegram_notifications_enabled
            ? ['text' => 'Pause notifications', 'callback_data' => 'nt:off']
            : ['text' => 'Enable notifications', 'callback_data' => 'nt:on'];

        if ($user->role === UserRole::Driver) {
            return [
                [['text' => 'My upcoming trips', 'callback_data' => 'menu:trips']],
                [$notificationButton],
            ];
        }

        return [
            [
                ['text' => 'Recent bookings', 'callback_data' => 'menu:recent'],
                ['text' => 'Pending', 'callback_data' => 'menu:pending'],
            ],
            [
                ['text' => "Today's bookings", 'callback_data' => 'menu:today'],
            ],
            [$notificationButton],
        ];
    }

    private function menuAction(User $user, string $chatId, string $action): void
    {
        match ($action) {
            'home' => $this->showMenu($user, $chatId),
            'help' => $this->showHelp($user, $chatId),
            'trips' => $this->driverTrips($user, $chatId),
            'recent', 'pending', 'today' => $this->adminBookings($user, $chatId, $action),
            default => throw new \RuntimeException('Unknown menu action.'),
        };
    }

    private function notificationAction(User $user, string $chatId, string $action): void
    {
        abort_unless(in_array($action, ['on', 'off'], true), 422);
        $this->setNotifications($user, $chatId, $action === 'on');
    }

    private function setNotifications(User $user, string $chatId, bool $enabled): void
    {
        $user->update(['telegram_notifications_enabled' => $enabled]);
        $this->client->sendMessage(
            $chatId,
            $enabled ? 'Telegram notifications enabled.' : 'Telegram notifications paused. Bot actions remain available.',
            $this->mainMenu($user->refresh()),
        );
    }

    private function adminBookings(User $user, string $chatId, string $filter): void
    {
        $this->operations($user);
        $query = Booking::query()->with(['tour.translations'])->latest();
        if ($filter === 'pending') {
            $query->where('booking_status', BookingStatus::Pending);
        } elseif ($filter === 'today') {
            $query->whereDate('booking_date', now()->toDateString());
        }
        $bookings = $query->limit(5)->get();
        if ($bookings->isEmpty()) {
            $this->client->sendMessage($chatId, 'No matching bookings found.', $this->mainMenu($user));

            return;
        }
        $bookings->each(fn (Booking $booking) => $this->adminBooking($chatId, $booking));
    }

    private function driverTrips(User $user, string $chatId): void
    {
        $driver = $this->driver($user);
        $bookings = Booking::query()->with(['tour.translations'])
            ->where('driver_id', $driver->id)
            ->whereNotIn('booking_status', [BookingStatus::Completed, BookingStatus::Cancelled, BookingStatus::NoShow])
            ->orderBy('starts_at')->limit(5)->get();
        if ($bookings->isEmpty()) {
            $this->client->sendMessage($chatId, 'You have no upcoming assigned trips.', $this->mainMenu($user));

            return;
        }
        $bookings->each(fn (Booking $booking) => $this->driverBooking($chatId, $booking));
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
