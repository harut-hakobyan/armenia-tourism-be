# Development Progress

Last updated: 2026-08-29

## Current position

Completed all 10 planned MVP phases.

Post-MVP enhancement completed: scheduled small-group tours alongside the original private-tour offering.

- Explicit `private` and `group` tour formats with API filtering
- Scheduled group departures with vehicle, driver, meeting point, capacity, and optional departure price
- Per-person authoritative estimates and transactional remaining-seat validation
- Multiple customer bookings can safely share one departure; overselling is prevented with row locking
- Two multilingual sample group tours and twelve upcoming departures
- Public Private/Group filters, format badges, per-person labels, departure selection, and group booking UX

1. Laravel backend foundation - complete
2. Catalog, fleet, translations, and seed data - complete
3. Pricing, promotions, routing, and availability - complete
4. Transactional booking system - complete
5. Admin and driver operations API - complete
6. Public catalog and estimation API - complete
7. React/TypeScript frontend foundation - complete
8. Public website and booking experience - complete
9. Admin and driver interfaces - complete
10. CMS, audit, hardening, Docker, CI, deployment, and documentation - complete

## Phase 10 delivered

Backend:

- Persisted reviews, multilingual FAQs, website settings, contact inquiries, and immutable audit history
- Public localized FAQ, public settings, verified-review, and rate-limited inquiry APIs
- Manager CMS APIs for customers, review moderation, promotions, FAQs, and inquiry workflow
- Admin-only settings and audit-history APIs
- Auditing for booking status, assignment, catalog visibility, CMS moderation, media, inquiries, promotions, and settings
- Validated JPEG/PNG/WebP media uploads capped at 10 MB and routed through Laravel storage for local/public or future S3 use
- Idempotent CMS seed data and production-safe role boundaries

Frontend:

- API-backed FAQ page and persisted contact form with WhatsApp fallback
- Admin customers, reviews, promotions, FAQ, inquiry, settings, and audit-history routes
- Promotion and FAQ creation, moderation/status controls, settings editor, and catalog image upload
- Settings and audit navigation visible only to administrators

Delivery:

- PHP 8.4 application image and Nginx configuration
- Docker Compose services for app, Nginx, MySQL 8.4, Redis 7, queue worker, and scheduler
- Production PHP limits, OPcache, security headers, health checks, and persistent data volumes
- Frontend multi-stage Node/Nginx production image with SPA routing and asset caching
- GitHub Actions quality gates for both repositories
- Installation, runtime, security, release, rollback, and operations documentation

## Verified state

- Fresh migration and all seeders passed from an empty SQLite database
- Backend formatting: 178 files passed
- Backend suite: 39 tests passed, 288 assertions
- Composer validation: strict validation passed
- Docker Compose configuration: valid
- Backend PHP 8.4 production image: built successfully
- Frontend strict TypeScript: passed
- Frontend ESLint: passed with no findings
- Frontend suite: 4 files, 8 tests passed
- Frontend production build: passed
- Frontend Nginx production image: built successfully with zero npm vulnerabilities reported

## Next practical milestone

The MVP development roadmap is complete. Before accepting real customer bookings:

1. Replace demonstration credentials, contact values, photographs, and translated FAQ copy
2. Configure the production domain, HTTPS, mail provider, backups, monitoring, and S3 if desired
3. Run a staging acceptance test with the actual cars, drivers, routes, prices, cancellation terms, and notification recipients
4. Choose and implement the first payment provider only if online deposit/full payment is required at launch
5. Perform an accessibility and device/browser acceptance pass with representative tourists and administrators

Suggested resume request:

> Continue from `docs/PROGRESS.md` and prepare the Armenia Tourism MVP for staging launch.
