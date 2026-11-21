# PixelForge Studio

A Laravel + Vue/Inertia SaaS for generating images through the **Pixelforge** API.

Users register, submit a prompt with a live cost estimate, and track each job from queued → processing → succeeded/failed. Costs use nanodollar precision with a **25% markup** on provider price. Webhooks drive status updates; a scheduled reconciler covers missed deliveries. A **Usage** page reconciles Pixelforge `GET /usage` against local succeeded costs. **My generations** supports typo-tolerant prompt search via Laravel Scout + Typesense (Postgres remains the source of truth).

---

## Product surfaces

| Page | Route | Purpose |
| --- | --- | --- |
| Welcome | `/` | Landing; guests register, signed-in users go to dashboard |
| Auth | `/login`, `/register` | Fortify (password reset is off until mail is configured) |
| Dashboard | `/dashboard` | Studio home |
| Generate | `/generations/create` | Prompt form + live estimate |
| History | `/generations` | Own generations only; optional `?q=` Typesense prompt search |
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

Stack inside Docker: PHP 8.4, PostgreSQL 16, Redis 8, Typesense 30.2 (search index), Node 22 (Vite), queue worker, scheduler.

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
| `TYPESENSE_API_KEY` | Prompt search | Must match the Compose Typesense service (default `pixelforgestudio`) |

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
| `typesense` | Search index (Scout) | **Not** published — only `typesense:8108` on the Compose network |
| `queue` | `queue:work` (webhook jobs) | — |
| `scheduler` | `schedule:work` (reconcile every minute) | — |

### 3. Install PHP deps, key, migrate, search index

Run Artisan **inside** the app container (`DB_HOST` is `postgres`, Typesense is `typesense:8108`):

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan scout:import "App\Models\Generation"
```

Import once after first boot (or after restoring a DB dump). New/updated/deleted generations sync to Typesense automatically while `SCOUT_DRIVER=typesense`.

### 4. Open the app

1. Visit http://localhost:8001  
2. Register  
3. **Start generating** → submit a prompt  
4. Open **My generations** and try prompt search (including a typo such as `huose` for `house`)  
5. Open **Usage** after some succeeded jobs to confirm provider vs local costs  

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
| `SCOUT_DRIVER` | `typesense` | Search engine (`null` in `phpunit.xml`) |
| `SCOUT_PREFIX` | empty | Optional index name prefix when sharing Typesense |
| `SCOUT_QUEUE` | `false` | Queue Scout sync jobs (consider `true` in production) |
| `TYPESENSE_API_KEY` | `pixelforgestudio` | Must match the Typesense container |
| `TYPESENSE_HOST` / `PORT` / `PROTOCOL` | `typesense` / `8108` / `http` | Compose service hostname |
| `TYPESENSE_PATH` | empty | Only if Typesense sits behind a reverse-proxy path |

Local defaults: `APP_URL=http://localhost:8001`, `QUEUE_CONNECTION=redis`, `DB_HOST=postgres` (in Docker).

---

## Prompt search (Scout + Typesense)

Postgres is the source of truth. Typesense is a **search index only** (not exposed on the host).

| Behavior | Detail |
| --- | --- |
| Empty / whitespace `q` | Normal Postgres history (`latest id`) |
| Non-empty `q` | Scout search on `prompt`, filtered by **auth `user_id`** (never from the client) |
| Typos | Configured in `config/scout.php` (`num_typos`, `min_len_1typo=3` so short typos like `dgo`→`dog` work) |
| UI | Debounced search on `/generations` (300ms); Clear restores full history |

Schema / search settings live under `config/scout.php` → `typesense.model-settings` for `Generation`. Changing the collection schema after documents exist usually means flush + re-import:

```bash
docker compose exec app php artisan scout:flush "App\Models\Generation"
docker compose exec app php artisan scout:import "App\Models\Generation"
```

If search returns nothing after a DB restore or before the first import, the index is empty — run `scout:import` again.

---

## Webhooks

Pixelforge POSTs status updates to your app when a generation starts, succeeds, or fails:

```text
POST /api/webhooks/pixelforge
```

Keep `app` + `queue` running. Put the signing secret in `.env` as `PIXELFORGE_WEBHOOK_SECRET`. If no webhook URL is set, the reconciler still updates jobs after ~60s.

**Local:** expose the app (e.g. `expose share http://localhost:8001`), then in Pixelforge → Webhooks paste:

```text
https://YOUR-EXPOSE-HOST/api/webhooks/pixelforge
```

**Production:** same idea — use the public server URL (open firewall port `8001` if needed):

```text
http://YOUR_HOST:8001/api/webhooks/pixelforge
```

Use a separate Pixelforge API key per environment when you can. Restart `app`/`queue` after changing the secret.

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

In-memory SQLite (`phpunit.xml`) — no Postgres/Redis required. Default `SCOUT_DRIVER=null` so most tests never call Typesense.

```bash
php artisan test --compact
docker compose exec app php artisan test --compact
```

`GenerationSearchTest` covers empty/`q` history, exact/partial match, auth isolation, and payload shape with the **collection** engine. Typo + index-sync cases need Typesense and **skip** when it is unreachable; run them inside Compose:

```bash
docker compose exec app php artisan test --compact tests/Feature/Controllers/GenerationSearchTest.php
```

Lefthook: **pre-commit** Pint `--test`; **pre-push** full Pest suite.

---

## Production checklist

1. HTTPS web server (`APP_URL` correct)  
2. Queue worker + scheduler every minute  
3. PostgreSQL migrated  
4. Typesense reachable; `TYPESENSE_*` set; `scout:import` after deploy/migrate  
5. `APP_DEBUG=false`, secrets set, webhook URL registered  
6. `npm run build`  
7. Smoke: register → generate → webhook/reconcile → one charge → search typo → Usage matched → `GET /up`  

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
| Prompt search | Scout `Searchable` on `Generation`, `config/scout.php` |
| Vue pages | `resources/js/Pages/{Welcome,Dashboard,Auth,generations,usage}` |
| Vue components | `resources/js/components/{generations,usage,ui}/` |
| Config | `config/pixelforge.php`, `config/scout.php` |
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
8. **Search scope** — Typesense queries always filter by the authenticated user; empty `q` never leaves Postgres.  

---

## Useful commands

```bash
docker compose up -d --build
docker compose logs -f app queue scheduler
docker compose exec app php artisan migrate
docker compose exec app php artisan scout:import "App\Models\Generation"
docker compose exec app php artisan pixelforge:reconcile-pending
docker compose exec app php artisan test --compact
./vendor/bin/pint --dirty
npx vue-tsc --noEmit -p tsconfig.json
```

---

## License

Application code follows the project’s repository license. Laravel framework components remain under the [MIT license](https://opensource.org/licenses/MIT).
