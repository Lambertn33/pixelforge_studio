# PixelForge Studio

A small Laravel SaaS for generating images through the **Pixelforge** API.

Users sign in, submit a prompt, see a live cost estimate, and track each generation from queued → processing → succeeded/failed. Money is tracked in nanodollar precision with a **25% markup** on provider cost. Webhooks drive status updates; a scheduled reconciler covers missed deliveries.

---

## How a generation flows

```text
1. User submits form
2. App estimates cost (GET /pricing) and saves a local Generation (queued)
3. App calls Pixelforge create (Idempotency-Key = client_request_id)
4. Pixelforge sends webhooks → verify HMAC → queue job → update status
5. On first transition to succeeded → charge ledger once
6. If a webhook is missed → scheduler polls GET /generations/{id} after ~60s
```

**UI note:** the detail page also polls Inertia for non-terminal generations so the screen updates without a manual refresh. Polling the browser does not replace webhooks or the server reconciler.

---

## Requirements

- Docker + Docker Compose
- A Pixelforge API key (and webhook secret for live webhooks)
- Optional: a public tunnel (ngrok, Cloudflare Tunnel, etc.) so Pixelforge can reach your local webhook URL

Stack inside Docker: PHP 8.4, PostgreSQL 16, Redis 8, Node 22 (Vite), queue worker, scheduler.

---

## Quick start (Docker)

### 1. Clone and env

```bash
cp .env.example .env
```

Edit `.env` and set at least:

| Variable | Required for | Notes |
| --- | --- | --- |
| `APP_KEY` | Boot | Generated in the next step |
| `PIXELFORGE_API_KEY` | Create / estimate | From the Pixelforge dashboard |
| `PIXELFORGE_WEBHOOK_SECRET` | Live webhooks | Same dashboard; leave empty until you configure a webhook |

Do **not** invent provider URLs or secrets. Defaults in `.env.example` match the project’s known Pixelforge base URL.

### 2. Start the stack

```bash
docker compose up -d --build
```

This starts:

| Service | Role | Host access |
| --- | --- | --- |
| `app` | Laravel HTTP (`artisan serve`) | http://localhost:8001 |
| `vite` | Frontend HMR | http://localhost:5173 |
| `postgres` | Database | localhost:5433 |
| `redis` | Queue + cache | localhost:6381 |
| `queue` | Runs `queue:work` (webhook jobs) | — |
| `scheduler` | Runs `schedule:work` (reconcile every minute) | — |

### 3. Install PHP deps, key, migrate

Run Artisan **inside** the app container (DB host is `postgres`, not `127.0.0.1`):

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

### 4. Open the app

1. Visit http://localhost:8001  
2. Register a user  
3. Go to **Generate** → submit a prompt  

Without a public webhook URL, generations often stay `queued`/`processing` until reconciliation (default: older than **60 seconds**). That is expected on localhost.

---

## Environment reference

Pixelforge-related keys (see `.env.example`):

| Key | Default | Meaning |
| --- | --- | --- |
| `PIXELFORGE_BASE_URL` | `https://pixelforge.promptedit.dev/v1` | API root |
| `PIXELFORGE_API_KEY` | empty | Bearer token for outbound API calls |
| `PIXELFORGE_WEBHOOK_SECRET` | empty | HMAC secret for inbound webhooks |
| `PIXELFORGE_WEBHOOK_TOLERANCE_SECONDS` | `300` | Reject signed payloads outside this window |
| `PIXELFORGE_RECONCILE_MIN_AGE_SECONDS` | `60` | Only poll unfinished gens older than this |
| `PIXELFORGE_RECONCILE_BATCH_SIZE` | `50` | Max gens per reconcile run |
| `PIXELFORGE_CONNECT_TIMEOUT` / `TIMEOUT` | `5` / `30` | HTTP timeouts (seconds) |
| `PIXELFORGE_RETRY_TIMES` / `RETRY_SLEEP_MS` | `3` / `200` | Transient retry policy |

App defaults that matter locally:

- `APP_URL=http://localhost:8001`
- `QUEUE_CONNECTION=redis`
- `DB_HOST=postgres` (from inside Docker)

---

## Webhooks (local)

Webhook endpoint (CSRF-exempt API route):

```text
POST {APP_URL}/api/webhooks/pixelforge
```

Local example with a tunnel:

```text
https://YOUR-TUNNEL.example/api/webhooks/pixelforge
```

### Setup checklist

1. Start Docker (`app` + `queue` must be running).  
2. Expose the app with a tunnel pointed at **http://localhost:8001**.  
3. In the Pixelforge dashboard, register that HTTPS webhook URL and copy the webhook secret.  
4. Set `PIXELFORGE_WEBHOOK_SECRET` in `.env` (and in `docker-compose` env if you override there).  
5. Restart app/queue so config reloads:

```bash
docker compose up -d app queue
```

### What the receiver does

1. Verify HMAC signature + timestamp tolerance  
2. Persist `WebhookEvent` (dedupe on `event_id`)  
3. Queue `ProcessWebhook`  
4. Resolve the local generation by `pixelforge_id` or `metadata.client_request_id`  
5. Apply status through a shared state machine (`StatusApplier`)  
6. Charge **once** on the first transition into `succeeded`

If a webhook arrives before the create response stores `pixelforge_id`, resolution still works via `client_request_id` in metadata, then backfills the provider id.

---

## Queue and scheduler

These are not optional in real use.

| Process | Command | Why |
| --- | --- | --- |
| Queue worker | `php artisan queue:work redis` | Processes webhook jobs |
| Scheduler | `php artisan schedule:work` (or cron `schedule:run`) | Runs `pixelforge:reconcile-pending` every minute |

Docker Compose already runs both as `queue` and `scheduler` services.

Manual reconcile (useful when testing without webhooks):

```bash
docker compose exec app php artisan pixelforge:reconcile-pending
```

Only unfinished generations with a `pixelforge_id` and age ≥ `PIXELFORGE_RECONCILE_MIN_AGE_SECONDS` are polled. Terminal states are never downgraded.

---

## Frontend

In Docker, `vite` runs `npm run dev` automatically.

On the host (optional):

```bash
npm install
npm run dev
```

Production build:

```bash
npm run build
```

If the UI shows “Unable to locate file in Vite manifest”, run `npm run build` or ensure `npm run dev` / the `vite` container is up.

---

## Tests

Tests use in-memory SQLite (see `phpunit.xml`). They do not need Postgres or Redis.

```bash
# Host (with vendor installed)
php artisan test

# Or inside Docker
docker compose exec app php artisan test
```

Compact / filtered:

```bash
php artisan test --compact
php artisan test --compact --filter=ProcessWebhook
```

Lefthook:

- **pre-commit** — Pint `--test`
- **pre-push** — full Pest suite

Keep the suite green before pushing.

---

## Production checklist

Use this when deploying (Laravel Cloud, VPS, etc.).

### Must run continuously

1. **Web server** serving HTTPS (`APP_URL` must match the public URL)  
2. **Queue worker** on Redis (or your chosen queue)  
3. **Scheduler** every minute (`* * * * * php artisan schedule:run`)  
4. **PostgreSQL** + migrations applied  

### Secrets and config

- `APP_KEY` set and stable  
- `APP_DEBUG=false`  
- `APP_ENV=production`  
- `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET` set from the provider dashboard  
- Webhook URL registered as `https://YOUR_DOMAIN/api/webhooks/pixelforge`  
- Redis reachable for `QUEUE_CONNECTION` / `CACHE_STORE`  
- Assets built (`npm run build`) and served  

### After deploy smoke test

1. Register / log in  
2. Submit a generation and confirm estimate → store → detail  
3. Confirm webhook delivery updates status (or wait for reconcile)  
4. Confirm a succeeded generation creates **one** credit charge  
5. Health check: `GET /up`  

---

## Project map (where things live)

| Area | Path |
| --- | --- |
| HTTP UI | `app/Http/Controllers/GenerationController.php` |
| Webhook entry | `app/Http/Controllers/Webhooks/PixelforgeController.php` |
| Submit + estimate | `app/Services/GenerationService.php` |
| Pixelforge HTTP client | `app/Services/Pixelforge/Client.php` |
| Webhook verify / receive | `app/Services/Pixelforge/WebhookSignatureVerifier.php`, `WebhookReceiver.php` |
| Webhook apply | `app/Services/Pixelforge/WebhookProcessor.php` |
| Shared status + money | `app/Services/Pixelforge/StatusApplier.php` |
| Pricing (nanodollars) | `app/Services/Pricing/PricingCalculator.php` |
| Reconcile | `app/Services/Pixelforge/Reconciler.php`, `pixelforge:reconcile-pending` |
| Vue pages | `resources/js/pages/generations/` |
| Vue components | `resources/js/components/generations/{form,list,item}/` |
| Config | `config/pixelforge.php` |
| Tests | `tests/Feature`, `tests/Unit` |

---

## Design guarantees (worth knowing)

1. **Local row first** — a Generation is persisted before calling Pixelforge.  
2. **Join key** — `client_request_id` in metadata is the reliable link when webhooks race the create response.  
3. **No terminal downgrade** — `processing` / `failed` after `succeeded` is ignored.  
4. **Charge once** — ledger write only on first transition into `succeeded`; duplicate succeeded events no-op.  
5. **Failed gens are not billed** — actual costs stored as zero; no charge row.  
6. **Money math** — integer nanodollars end-to-end; customer cost = provider × 5/4.  

---

## Useful commands

```bash
# Stack
docker compose up -d --build
docker compose logs -f app queue scheduler
docker compose exec app php artisan migrate
docker compose exec app php artisan pixelforge:reconcile-pending

# Quality
docker compose exec app php artisan test --compact
./vendor/bin/pint --dirty
npx vue-tsc --noEmit -p tsconfig.json
```

---

## License

Application code follows the project’s repository license. Laravel framework components remain under the [MIT license](https://opensource.org/licenses/MIT).
