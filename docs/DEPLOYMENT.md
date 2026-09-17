# Production deployment

## Recommended topology

- Ubuntu host with Docker Engine and Compose
- TLS termination at a host reverse proxy or managed load balancer
- `nginx`, `app`, `queue`, and `scheduler` containers from this repository
- persistent MySQL and Redis volumes, with external managed services preferred at scale
- Next.js frontend container built from the sibling `armenia-tourism-fe` repository

## First deployment

1. Copy `.env.example` to `.env` and set `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, production URLs, database passwords, mail credentials, and administrator credentials.
2. Set `FRONTEND_URL=https://tour-armenia.com`, `SITE_URL=https://tour-armenia.com`, and `SANCTUM_STATEFUL_DOMAINS=tour-armenia.com`. Do not include URL paths in the stateful-domain value.
3. Build and start the services:

```bash
docker compose build --pull
docker compose up -d mysql redis frontend app queue scheduler nginx
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
```

Seeders contain local demonstration content and credentials. Run `php artisan db:seed --force` only when that data is intentionally wanted.

## Release procedures

Backend and frontend releases are independent. A backend release must not fetch,
build, or recreate the frontend, and a frontend release must not fetch, build, or
recreate the Laravel application, queue, or scheduler.

### Backend release

```bash
git pull --ff-only
docker compose build app queue scheduler
docker compose up -d --wait --no-deps --force-recreate app queue scheduler
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=Database\\Seeders\\PersianContentSeeder --force
docker compose exec app php artisan optimize
docker compose exec nginx nginx -s reload
```

### Frontend release

Run these commands from the backend repository after updating only the sibling
`armenia-tourism-fe` checkout:

```bash
docker compose build frontend
docker compose up -d --wait --no-deps --force-recreate frontend
docker compose exec nginx nginx -s reload
```

The frontend uses `NEXT_PUBLIC_API_BASE_URL=/api/v1`; server rendering reaches Laravel through `LARAVEL_INTERNAL_API_URL=http://nginx/api/v1`. Set `SITE_URL=https://tour-armenia.com` before building or starting the production stack.

After the containers are healthy, run the frontend's external checks from a machine outside the server:

```bash
cd ../armenia-tourism-fe
npm run test:live
NEXT_SMOKE_ORIGIN=https://tour-armenia.com npm run test:seo
```

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
- Replace the old static-React virtual host with `docs/nginx-host-tour-armenia.conf.example`, verify the certificate paths, run `nginx -t`, and reload the host Nginx service.
- Keep `APP_DEBUG=false`; never expose `.env`, logs, or storage internals.
- Public booking, estimate, contact, review, and login endpoints are rate limited.
- Uploaded images are restricted to JPEG, PNG, and WebP, validated by content, capped at 10 MB, renamed with UUIDs, and stored through Laravel's filesystem abstraction.
- Run `composer audit` and `npm audit --audit-level=high` in the release pipeline or dependency-update workflow.
- Give managers operational access only; settings and audit history remain admin-only.

## Rollback

Deploy the previous immutable image and restore a compatible database backup when a migration is not backward compatible. Avoid automatic `migrate:rollback` in production because migrations may contain irreversible data transformations.
