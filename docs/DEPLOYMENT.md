# Production deployment

## Recommended topology

- Ubuntu host with Docker Engine and Compose
- TLS termination at a host reverse proxy or managed load balancer
- `nginx`, `app`, `queue`, and `scheduler` containers from this repository
- persistent MySQL and Redis volumes, with external managed services preferred at scale
- React frontend built separately and served from its Nginx image or a CDN

## First deployment

1. Copy `.env.example` to `.env` and set `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, production URLs, database passwords, mail credentials, and administrator credentials.
2. Set `FRONTEND_URL` and `SANCTUM_STATEFUL_DOMAINS` to the real HTTPS frontend origin. Do not include URL paths in the stateful-domain value.
3. Build and start the services:

```bash
docker compose build --pull
docker compose up -d mysql redis app queue scheduler nginx
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
```

Seeders contain local demonstration content and credentials. Run `php artisan db:seed --force` only when that data is intentionally wanted.

## Release procedure

```bash
git pull --ff-only
docker compose build app queue scheduler
docker compose up -d --no-deps app queue scheduler
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

Build the frontend with `VITE_API_BASE_URL=https://api.example.com/api/v1`. Its Nginx configuration supports React Router fallback and immutable asset caching.

## Operations

- Health endpoints: `/up` for Laravel and `/api/v1/health` for the versioned API.
- Queue workers use bounded `--max-time` lifetimes so Docker can recycle them safely.
- The scheduler runs as one dedicated service. Run only one scheduler replica unless scheduler locks are added to every command.
- Back up MySQL and uploaded storage. Redis append-only persistence is enabled but Redis is not the booking source of truth.
- Monitor HTTP 5xx responses, failed jobs, queue depth, disk usage, database connections, and TLS expiry.
- Rotate `APP_KEY` only with a migration plan because it invalidates encrypted application data and deterministic booking access tokens.

## Security checklist

- Use generated secrets and restrict database/Redis forwarded ports at the firewall; remove their host port mappings when external access is unnecessary.
- Terminate TLS and redirect HTTP to HTTPS at the edge.
- Keep `APP_DEBUG=false`; never expose `.env`, logs, or storage internals.
- Public booking, estimate, contact, review, and login endpoints are rate limited.
- Uploaded images are restricted to JPEG, PNG, and WebP, validated by content, capped at 10 MB, renamed with UUIDs, and stored through Laravel's filesystem abstraction.
- Run `composer audit` and `npm audit --audit-level=high` in the release pipeline or dependency-update workflow.
- Give managers operational access only; settings and audit history remain admin-only.

## Rollback

Deploy the previous immutable image and restore a compatible database backup when a migration is not backward compatible. Avoid automatic `migrate:rollback` in production because migrations may contain irreversible data transformations.
