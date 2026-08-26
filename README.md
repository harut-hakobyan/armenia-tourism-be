# Armenia Tourism API

Production-oriented Laravel REST API for an Armenia private-tour, airport-transfer, private-driver and custom-trip booking platform.

The backend is intentionally separate from the React frontend in `../armenia-tourism-fe`. Its versioned API is designed for the web application, future mobile clients, and driver/admin integrations.

## Current status

Phases 1 and 2 are complete:

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
- complete proposed architecture and MVP roadmap in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)

Pricing behavior, availability, booking transactions, public APIs, and administration remain scheduled for subsequent tested phases.

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
```

Authenticated requests use `Authorization: Bearer <token>`. The full planned public/admin/driver endpoint map is documented in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Quality checks

```bash
vendor/bin/pint --test
php artisan test
composer validate --strict
```

Tests use an in-memory SQLite database and do not require MySQL or Redis.
