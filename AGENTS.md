# Yolt-App (Laravel)

The company app. It was **rebuilt from scratch** on Laravel — a clean
re-implementation of the old Next.js/Supabase app — and the **data cutover ran and
was verified clean on 2026-09-09**, so this Laravel app is now the **live app on the
real data**. **The authoritative plan, decisions, and progress log live in
`Stack Change Plan.md` in this repo**; the app's feature behavior is in `Summary.md`
— read both for the full context and roadmap.

This is a fresh, idiomatic Laravel build. **Do not port** code from the old app —
look at it only as a behavioural reference, then build clean. The old app
(`Angel-Pappas/yolt-app`, on Vercel + Supabase) is a read-only fallback pending
retirement — don't work in it.

## Stack (verified 2026-08-28)

- **Laravel 13** (PHP **8.4** locally / **8.5** in production on Laravel Cloud),
  official **React starter kit**: React 19, TypeScript, **Inertia 3**,
  **Tailwind 4**, **shadcn/ui** (new-york, neutral, **Instrument Sans**, **lucide**
  icons, light + dark), Vite (vite-plus), **Fortify** auth, **Pest** tests.
- **Database:** MySQL 8.4 in production (Laravel Cloud); **SQLite locally** for now
  (align local to MySQL once we build data-heavy features).
- Package manager: **pnpm**.

## Running it

- Install: `composer install`, `pnpm install`.
- Dev: `php artisan serve` + `pnpm run dev`.
- Tests: `php artisan test` (Pest).
- Format/lint: `pnpm run check` (CI runs this) — run **`pnpm run check:fix` before
  pushing**, or CI fails on formatting.
- Migrations: `php artisan migrate`.

## Deploy

- **Push to `main` → Laravel Cloud auto-deploys** (push-to-deploy). Live at
  `yolt-app-new-production-ximjo9.laravel.cloud`.
- GitHub Actions CI runs tests + formatting on every push — **keep it green**.
- On this Windows dev machine `php`/`composer`/`pnpm` may be missing from a fresh
  shell's PATH; refresh PATH (machine + user) or prepend the winget PHP dir.

## Laravel Cloud MCP (managing hosting from Claude Code)

Claude Code can manage this app's hosting directly through the **`laravel-cloud`
MCP server** (list apps/environments/deployments, trigger deploys, read/set env
vars, run artisan commands) — the same way the Supabase MCP works for the old app.

- **How it's wired:** the server is defined in the user's `~/.claude.json` and
  reads its bearer token from a **`LARAVEL_CLOUD_API_TOKEN`** environment variable.
  The token is set **persistently in the Windows User environment** (via `setx`), so
  it survives reboots.
- **401 Unauthorized fix:** a 401 from the MCP almost always means the env var is
  empty in the running process — either it was never set, or the desktop app was
  launched **before** the variable existed (the MCP server is spawned at app start
  and captures the environment then). Fix: ensure the var is set
  (`setx LARAVEL_CLOUD_API_TOKEN "<token>"`), then **fully quit and reopen** the
  Claude Code desktop app so it re-reads the environment and re-spawns the server.
  A new shell/tab is **not** enough — it must be a full app restart.
- **Rotating the token:** create a new token in Laravel Cloud → Organization
  Settings → API Tokens, delete the old one, then `setx LARAVEL_CLOUD_API_TOKEN
"<new>"` and restart the app once.
- App: `yolt-app-new` (`eu-central-1` / Frankfurt); production env vanity domain
  `yolt-app-new-production-ximjo9.laravel.cloud`.
- Source: <https://mcp.laravel.cloud/>.

## Working rules (from the plan)

1. **Verify, don't trust memory** — check current docs before stating a
   version/API/approach.
2. **Tests for everything that warrants one** (Pest) — especially money/tax math
   and authorization.
3. **Build clean & idiomatic** — no ports, no Next.js shapes; use framework
   conventions and shadcn components.
4. **Design = the starter kit's shadcn look**, not the old app's identity. Parity
   is _functional_, not a visual pixel-match.

## Access control

Company access lives on `users`: `is_admin`, `can_access_finance`,
`can_access_crm`, `is_active` (new users: no access, active). Gates: `admin`,
`access-finance`, `access-crm` (each also requires `is_active`).
`EnsureAccountIsActive` middleware logs out deactivated users on every request.
Protect routes with the `can:` middleware (e.g. `can:access-finance`).

## Established patterns (match these — don't reinvent)

- **Uniform list view** (`resources/js/components/data-table/`): EVERY list/table page
  renders through the shared `DataTable` — a uniform header row (`title` left; search +
  page controls + Add clustered right), an optional `controls` row (Transactions' date
  pickers / All-time·This-month·Last-month presets / reconcile·invoice toggles), and
  **per-column header filters**. To make a column filterable, declare
  `meta: { filter: { type: 'text' | 'select' | 'number' | 'date', options? } }` on it
  and use `ColumnHeader` for the header — the funnel + popover (`column-filter.tsx`,
  Radix-portalled so it isn't clipped) and the matching filter fn are wired
  automatically. Filtering/search/sort/pagination are **client-side** (TanStack); only
  scope-defining filters stay server-side (Transactions' date/invoice/quick/balance;
  Leads' hide-converted default). A NEW list page MUST use this — never hand-roll a
  `<table>` or a bespoke filter bar.
- **Simple lookup CRUD** (Entities, Categories, VAT/Withheld rates): a controller
  with `index/store/update/destroy`, gated `can:access-finance`, sets a created-by
  `user_id` on store, uses `$request->validate(...)`, flashes a toast via
  `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])`, and
  soft-deletes on `destroy`. The frontend uses the reusable
  `resources/js/components/crud/crud-resource.tsx` (config-driven table + add/edit
  dialog + delete; text/decimal/select fields) — Categories/VAT/Withheld are thin
  config pages. Wallets/Entities are hand-written pages (their own add/edit dialog) but
  now render their list through `DataTable` like everything else; a NEW lookup should
  use `CrudResource`.
- **Transactions** (`TransactionController`): `validateTransaction()` (rules branch
  on type — income/expense carry a `lines[]` array; transfer needs `to_wallet_id`
  `different` from `wallet_id`), `persist()` (fills fields + rewrites VAT lines
  wholesale; nulls the other shape's fields on a type change), `resolveLines()`
  (**VAT is always computed server-side from the rate's current % — never trusted
  from the client**). Form: `transaction-form-dialog.tsx` (create + edit; the list
  remounts it via a `key` bump for a fresh form).
- **Money & dates:** `resources/js/lib/format.ts` — `formatAmount` (Greek
  `1.234,56`) + `formatDate` (`dd/mm/yyyy`). `numeric`/`decimal` columns arrive as
  **strings** → `Number(...)` before math; money is `decimal(12,2)`. Amount inputs
  accept comma or dot; normalize `,`→`.` before submit.
- **Wallet balances:** `App\Support\WalletBalances::all()` derives balances live
  (never stored). Reuse it (e.g. the transactions balance-view slice).
- **Schema:** bigint PKs, `foreignId(...)->constrained()`, `softDeletes()`,
  `timestamps()`, a nullable `user_id` audit FK on shared tables. Models use the
  Laravel-13 `#[Fillable([...])]` attribute + a `casts()` method. Existing UUIDs are
  remapped to new IDs at the cutover conversion (plan §5/§7).
- **Factory states:** `User::factory()` has `admin()`, `withFinanceAccess()`,
  `withCrmAccess()`, `inactive()`.

## Local verify — ALL must pass before every push (this is what CI runs)

From the app dir, with php/composer/pnpm on PATH:

```
pnpm run check:fix              # format + lint (auto-fixes); CI's `pnpm run check` FAILS on any warning
pnpm run types:check            # tsc --noEmit
php vendor/bin/pint --parallel  # PHP formatting (CI runs pint --test)
php vendor/bin/phpstan analyse  # PHP static analysis
pnpm run build                  # so NEW Inertia pages enter the Vite manifest
php artisan test                # Pest
```

- **Gotcha:** a full-page GET test (`assertOk`) that renders a **new** Inertia page
  fails with a Vite-manifest error until you `pnpm run build`. Build before running
  those tests locally (CI builds via `composer setup` first).
- **CI = `composer ci:check`** (npm check + tsc + pint --test + phpstan + pest).
- **Windows PATH:** php is the winget package under
  `C:\Users\Sofoklis\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_*`; a fresh
  shell may not see it — refresh PATH (Machine+User) and prepend that dir. Its
  `php.ini` exists with `memory_limit = 512M` (PHPStan needs it).
- After pushing: `gh run watch --repo Angel-Pappas/yolt-app-new <id> --exit-status`.

## How to continue (fresh session)

1. Read **`Stack Change Plan.md`** (in this repo) — its **§20 progress log** lists
   exactly what's built and the next slices.
2. Pick the next slice, build it idiomatically (match the patterns above), write
   tests, run the full local verify, commit + push to `main`, watch CI green.
3. Keep each slice small and green; log the increment in the plan's §20.
