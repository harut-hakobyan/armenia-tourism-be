# Armenia Tourism API

Production-oriented Laravel REST API for an Armenia private-tour, scheduled group-tour, airport-transfer, private-driver and custom-trip booking platform.

The backend is intentionally separate from the React frontend in `../armenia-tourism-fe`. Its versioned API is designed for the web application, future mobile clients, and driver/admin integrations.

Current development handoff: [docs/PROGRESS.md](docs/PROGRESS.md)

## Current status

The ten-phase MVP is implemented:

- Laravel 12 with a PHP 8.4 platform requirement
- Laravel Sanctum API-token authentication
- versioned `/api/v1` routes
- typed user and booking-domain enums
- admin/manager/driver/customer role middleware
- local administrator seeder
- authentication and authorization tests
- multilingual destinations and tour categories
- cars, drivers, preferred/authorized driver-car assignments
- single-day and multi-day tours with ordered itinerary stops
- tour price matrices prepared for the pricing engine
- polymorphic media metadata for covers, galleries, and profile photos
- idempotent seeders with 15 destinations, 8 private tours, 2 group tours, 12 upcoming group departures, 6 cars, and 2 drivers
- catalog relationship and schema tests
- canonical customer, promotion, and booking-calendar persistence
- server-authoritative tour, transfer, private-driver, and custom-trip pricing
- promo-code validation with percentage, fixed, minimum-order, cap, date, and usage rules
- provider-neutral route calculation with an offline Haversine MVP adapter
- overlap-safe car and driver availability queries
- pricing, routing, promotion, and availability tests
- transactional booking creation for tours, transfers, private drivers, and custom trips
- concurrency-safe yearly booking numbers and idempotency claims
- deterministic secure customer access tokens stored only as hashes
- immutable service, route, tour, customer, and price snapshots
- booking status history and validated workflow transitions
- after-commit booking events and queued admin/customer email notifications
- public booking creation and secure booking-status endpoints
- policy-protected admin/manager booking lists, filters, calendar, and details
- conflict-safe car/driver assignment with authorized vehicle validation
- controlled admin booking and driver trip status workflows with history
- assigned-trip APIs for drivers and queued driver assignment notifications
- locale-aware public destination, category, tour, itinerary, and car APIs
- paginated catalog filtering and sorting with active-only public visibility
- server-authoritative tour, transfer, private-driver, and custom-trip estimate APIs
- estimate-to-booking parity through shared pricing, promotion, and routing services
- React-backed admin and driver operational APIs
- persisted customers, reviews, promotions, multilingual FAQs, contact inquiries, and website settings
- admin-only settings and audit history with auditable assignment, status, visibility, moderation, and CMS changes
- validated provider-neutral media uploads with public-storage and S3-compatible architecture
- Docker services for PHP-FPM, Nginx, MySQL, Redis, queues, and the scheduler
- backend/frontend GitHub Actions quality gates and production deployment guidance
- complete proposed architecture and MVP roadmap in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)

Online payment-provider implementations, live tracking, partner services, bots, hotels, and mobile apps remain intentionally outside the MVP.

## Requirements

- PHP 8.4+
- Composer 2
- MySQL 8.4+
- Redis 7+

Docker is the recommended local runtime. A host PHP installation remains supported.

## Docker setup

```bash
cp .env.example .env
docker compose build
docker run --rm armenia-tourism-be-app php artisan key:generate --show
# Paste the displayed value into APP_KEY in .env
docker compose up -d
docker compose exec app php artisan migrate --seed
```

The API is available at `http://localhost:8000`. See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) before deploying production credentials or data.

## Local installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Configure MySQL and Redis credentials in `.env` before running migrations. Never use the example administrator password outside local development.

Local seeded administrator defaults:

```text
Email: admin@armeniatourism.local
Password: ChangeMe123!
```

Override both values with `ADMIN_EMAIL` and `ADMIN_PASSWORD`.

## API foundation

```text
GET  /api/v1/health
POST /api/v1/auth/login
GET  /api/v1/auth/me
POST /api/v1/auth/logout
GET  /api/v1/admin/health
GET  /api/v1/destinations
GET  /api/v1/destinations/{slug}
GET  /api/v1/tour-categories
GET  /api/v1/tour-categories/{slug}
GET  /api/v1/tour-categories/{slug}/tours
GET  /api/v1/tours
GET  /api/v1/tours/{slug}
GET  /api/v1/cars
GET  /api/v1/cars/{id}
GET  /api/v1/faqs
GET  /api/v1/settings
GET  /api/v1/reviews
POST /api/v1/reviews
POST /api/v1/contact-inquiries
POST /api/v1/pricing/tours/estimate
POST /api/v1/transfers/estimate
POST /api/v1/private-driver/estimate
POST /api/v1/custom-trips/estimate
POST /api/v1/bookings
GET  /api/v1/bookings/{bookingNumber}/{secureToken}
GET  /api/v1/admin/bookings
GET  /api/v1/admin/bookings/calendar
GET  /api/v1/admin/bookings/{booking}
POST /api/v1/admin/bookings/{booking}/confirm
POST /api/v1/admin/bookings/{booking}/assign
POST /api/v1/admin/bookings/{booking}/status
POST /api/v1/admin/bookings/{booking}/cancel
GET  /api/v1/admin/customers
GET  /api/v1/admin/reviews
GET  /api/v1/admin/promo-codes
GET  /api/v1/admin/faqs
GET  /api/v1/admin/inquiries
GET  /api/v1/admin/settings
GET  /api/v1/admin/audit-logs
POST /api/v1/admin/media/{type}/{id}
GET  /api/v1/driver/trips
GET  /api/v1/driver/trips/{booking}
POST /api/v1/driver/trips/{booking}/status
```

Authenticated requests use `Authorization: Bearer <token>`. The full planned public/admin/driver endpoint map is documented in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Telegram operations bot

Admins, managers, and drivers can securely link a private Telegram chat from the Telegram page in their web operations panel. Links are single-use and expire after 15 minutes.

Configure the bot created with BotFather:

```env
TELEGRAM_BOT_TOKEN=123456:replace-with-bot-token
TELEGRAM_BOT_USERNAME=YourArmeniaBot
TELEGRAM_WEBHOOK_SECRET=replace-with-a-long-random-secret
```

Apply the migration, restart the application and queue worker, then register a public HTTPS backend URL (an ngrok backend URL works for development):

```bash
php artisan migrate
php artisan telegram:webhook --url=https://api.example.com
```

The webhook endpoint is `/api/v1/telegram/webhook`. Telegram requests must contain the configured `X-Telegram-Bot-Api-Secret-Token`. Incoming updates and outgoing messages use Laravel queues, so the queue worker must be running.

Admin/manager bot actions include recent booking lists, details, confirmation, cancellation, and guided available-car/driver assignment. Drivers receive assigned trips and can advance through `on_the_way`, `arrived`, `passenger_picked_up`, `trip_started`, and `completed`.

Remove the registered webhook with `php artisan telegram:webhook --remove`.

## Quality checks

```bash
vendor/bin/pint --test
php artisan test
composer validate --strict
```

Tests use an in-memory SQLite database and do not require MySQL or Redis.
