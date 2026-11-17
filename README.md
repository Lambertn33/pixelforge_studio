# PixelForge Studio

A Laravel + Vue/Inertia SaaS for generating images through the **Pixelforge** API.

Users register, submit a prompt with a live cost estimate, and track each job from queued → processing → succeeded/failed. Costs use nanodollar precision with a **25% markup** on provider price. Webhooks drive status updates; a scheduled reconciler covers missed deliveries. A **Usage** page reconciles Pixelforge `GET /usage` against local succeeded costs.

---

## Product surfaces

| Page | Route | Purpose |
| --- | --- | --- |
| Welcome | `/` | Landing; guests register, signed-in users go to dashboard |
| Auth | `/login`, `/register` | Fortify (password reset is off until mail is configured) |
| Dashboard | `/dashboard` | Studio home |
| Generate | `/generations/create` | Prompt form + live estimate |
| History | `/generations` | Own generations only |
| Detail | `/generations/{id}` | Preview, costs, token usage; polls while non-terminal |
| Usage | `/usage` | Compare provider billing vs local ledger costs (stretch) |

---

## How a generation flows

```text
1. User submits form
2. App estimates cost (GET /pricing) and saves a local Generation (queued)
3. App calls Pixelforge create (Idempotency-Key = client_request_id)
4. Pixelforge sends webhooks → verify HMAC → queue job → update status
5. On first transition to succeeded → write CreditTransaction once + store usage/costs
6. If a webhook is missed → scheduler polls GET /generations/{id} after ~60s
```

**UI note:** the detail page polls Inertia for non-terminal generations. Browser polling does not replace webhooks or the server reconciler.

---

## Money and the credit ledger

| Concept | Where |
| --- | --- |
| Estimated cost | From live `GET /pricing` before submit |
| Actual provider cost | From generation/webhook **usage** × frozen rates |
| Actual customer cost | Provider × **5/4** (25% markup) |
| `CreditTransaction` | Append-only ledger: charge on first `succeeded`; unique `idempotency_key` prevents double charge |
| Failed generations | `$0` actual costs; **no** charge row |

`CreditTransaction` is the internal money audit trail (balance via latest `balance_after`). It is not a separate billing UI yet — costs also appear on generation detail and the Usage page.

---

## Usage reconciliation (`GET /v1/usage`)

Pixelforge bills per API key by the UTC day a generation **completed**. The Usage page:

1. Calls `GET /usage` (optional `start_date` / `end_date`, max 92 days)
2. Sums local **succeeded** rows by UTC `completed_at`
3. Compares provider `cost_usd` vs local `actual_provider_cost` (and generation counts)
4. Shows matched / mismatch per day and in total

Customer totals on that page include markup; provider totals should match Pixelforge.

---

## Requirements

- Docker + Docker Compose
- A Pixelforge API key (and webhook secret for live webhooks)
- Optional: a public tunnel so Pixelforge can reach your local webhook URL

Stack inside Docker: PHP 8.4, PostgreSQL 16, Redis 8, Node 22 (Vite), queue worker, scheduler.

---

## Quick start (Docker)

### 1. Clone and env

```bash
cp .env.example .env
```

| Variable | Required for | Notes |
| --- | --- | --- |
| `APP_KEY` | Boot | Generated in the next step |
| `PIXELFORGE_API_KEY` | Create / estimate / usage | From the Pixelforge dashboard |
| `PIXELFORGE_WEBHOOK_SECRET` | Live webhooks | Same dashboard; leave empty until configured |

### 2. Start the stack

```bash
docker compose up -d --build
```

| Service | Role | Host access |
| --- | --- | --- |
| `app` | Laravel HTTP | http://localhost:8001 |
| `vite` | Frontend HMR | http://localhost:5173 |
| `postgres` | Database | localhost:5433 |
| `redis` | Queue + cache | localhost:6381 |
| `queue` | `queue:work` (webhook jobs) | — |
| `scheduler` | `schedule:work` (reconcile every minute) | — |

### 3. Install PHP deps, key, migrate

Run Artisan **inside** the app container (`DB_HOST` is `postgres`):

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

### 4. Open the app

1. Visit http://localhost:8001  
2. Register  
3. **Start generating** → submit a prompt  
4. Open **Usage** after some succeeded jobs to confirm provider vs local costs  

Without a public webhook URL, jobs often stay pending until reconciliation (~**60s**+). That is expected on localhost.

---

## Environment reference

| Key | Default | Meaning |
| --- | --- | --- |
| `PIXELFORGE_BASE_URL` | `https://pixelforge.promptedit.dev/v1` | API root |
| `PIXELFORGE_API_KEY` | empty | Bearer token |
| `PIXELFORGE_WEBHOOK_SECRET` | empty | HMAC secret for inbound webhooks |
| `PIXELFORGE_WEBHOOK_TOLERANCE_SECONDS` | `300` | Reject signed payloads outside this window |
| `PIXELFORGE_RECONCILE_MIN_AGE_SECONDS` | `60` | Only poll unfinished gens older than this |
| `PIXELFORGE_RECONCILE_BATCH_SIZE` | `50` | Max gens per reconcile run |
| `PIXELFORGE_CONNECT_TIMEOUT` / `TIMEOUT` | `5` / `30` | HTTP timeouts (seconds) |
| `PIXELFORGE_RETRY_TIMES` / `RETRY_SLEEP_MS` | `3` / `200` | Transient retry policy |

Local defaults: `APP_URL=http://localhost:8001`, `QUEUE_CONNECTION=redis`, `DB_HOST=postgres` (in Docker).

---

## Webhooks (local)

```text
POST {APP_URL}/api/webhooks/pixelforge
```

Example: `https://YOUR-TUNNEL.example/api/webhooks/pixelforge`

1. Start Docker (`app` + `queue`).  
2. Tunnel → **http://localhost:8001**.  
3. Register the HTTPS URL + secret in the Pixelforge dashboard.  
4. Set `PIXELFORGE_WEBHOOK_SECRET` in `.env`.  
5. `docker compose up -d app queue`

Receiver flow: verify HMAC → persist/dedupe `WebhookEvent` → queue `ProcessWebhook` → resolve by `pixelforge_id` or `metadata.client_request_id` → `StatusApplier` → charge once on first `succeeded`.

---

## Queue and scheduler

| Process | Command | Why |
| --- | --- | --- |
| Queue worker | `php artisan queue:work redis` | Webhook jobs |
| Scheduler | `php artisan schedule:work` (or cron `schedule:run`) | `pixelforge:reconcile-pending` every minute |

```bash
docker compose exec app php artisan pixelforge:reconcile-pending
```

Only unfinished generations with a `pixelforge_id` and age ≥ min age are polled. Terminal states never downgrade.

---

## Frontend

Docker `vite` runs `npm run dev`. On the host: `npm install && npm run dev`. Production: `npm run build`.

If you see “Unable to locate file in Vite manifest”, build assets or start Vite.

---

## Tests

In-memory SQLite (`phpunit.xml`) — no Postgres/Redis required.

```bash
php artisan test --compact
docker compose exec app php artisan test --compact
```

Lefthook: **pre-commit** Pint `--test`; **pre-push** full Pest suite.

---

## Production checklist

1. HTTPS web server (`APP_URL` correct)  
2. Queue worker + scheduler every minute  
3. PostgreSQL migrated  
4. `APP_DEBUG=false`, secrets set, webhook URL registered  
5. `npm run build`  
6. Smoke: register → generate → webhook/reconcile → one charge → Usage matched → `GET /up`  

---

## Project map

| Area | Path |
| --- | --- |
| Routes | `routes/web.php`, `routes/api.php` |
| Home / dashboard | `HomeController`, `DashboardController` |
| Generations UI API | `GenerationController` |
| Usage reconcile UI | `UsageController`, `UsageReconciler` |
| Webhooks | `Webhooks\PixelforgeController`, `WebhookReceiver`, `WebhookProcessor` |
| Submit + estimate | `GenerationService` |
| Pixelforge HTTP | `Services\Pixelforge\Client` |
| Status + ledger charge | `StatusApplier`, `CreditTransaction` |
| Pricing | `PricingCalculator` |
| Pending poll | `Reconciler`, `pixelforge:reconcile-pending` |
| Vue pages | `resources/js/pages/{Welcome,Dashboard,auth,generations,usage}` |
| Vue components | `resources/js/components/{generations,usage,ui}/` |
| Config | `config/pixelforge.php` |
| Tests | `tests/Feature`, `tests/Unit` |

---

## Design guarantees

1. **Local row first** — Generation persisted before Pixelforge create.  
2. **Join key** — `client_request_id` in metadata for early webhooks.  
3. **No terminal downgrade** — ignore late `processing` / `failed` after `succeeded`.  
4. **Charge once** — ledger only on first transition into `succeeded`.  
5. **Failed = free** — zero actual costs; no charge row.  
6. **Exact money** — nanodollars; customer = provider × 5/4.  
7. **Usage truth** — Pixelforge `GET /usage` is account/key billing; local sums should match provider cost.  

---

## Useful commands

```bash
docker compose up -d --build
docker compose logs -f app queue scheduler
docker compose exec app php artisan migrate
docker compose exec app php artisan pixelforge:reconcile-pending
docker compose exec app php artisan test --compact
./vendor/bin/pint --dirty
npx vue-tsc --noEmit -p tsconfig.json
```

---

## License

Application code follows the project’s repository license. Laravel framework components remain under the [MIT license](https://opensource.org/licenses/MIT).
