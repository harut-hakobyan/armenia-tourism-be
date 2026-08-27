# Armenia Tourism API

Production-oriented Laravel REST API for an Armenia private-tour, airport-transfer, private-driver and custom-trip booking platform.

The backend is intentionally separate from the React frontend in `../armenia-tourism-fe`. Its versioned API is designed for the web application, future mobile clients, and driver/admin integrations.

Current development handoff: [docs/PROGRESS.md](docs/PROGRESS.md)

## Current status

Phases 1 through 5 are complete:

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
- idempotent seeders with 15 destinations, 8 tours, 6 cars, and 2 drivers
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
- complete proposed architecture and MVP roadmap in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)

Remaining catalog/admin modules, public estimation APIs, payments, and the React applications remain scheduled for subsequent tested phases.

## Requirements

- PHP 8.4+
- Composer 2
- MySQL 8.4+
- Redis 7+

Docker Compose application services will be added in the delivery phase. Until then, Composer's official image can run checks on a host with Docker.

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
POST /api/v1/bookings
GET  /api/v1/bookings/{bookingNumber}/{secureToken}
GET  /api/v1/admin/bookings
GET  /api/v1/admin/bookings/calendar
GET  /api/v1/admin/bookings/{booking}
POST /api/v1/admin/bookings/{booking}/confirm
POST /api/v1/admin/bookings/{booking}/assign
POST /api/v1/admin/bookings/{booking}/status
POST /api/v1/admin/bookings/{booking}/cancel
GET  /api/v1/driver/trips
GET  /api/v1/driver/trips/{booking}
POST /api/v1/driver/trips/{booking}/status
```

Authenticated requests use `Authorization: Bearer <token>`. The full planned public/admin/driver endpoint map is documented in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Quality checks

```bash
vendor/bin/pint --test
php artisan test
composer validate --strict
```

Tests use an in-memory SQLite database and do not require MySQL or Redis.
