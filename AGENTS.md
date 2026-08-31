# Yolt-App (Laravel rebuild)

The company app, being **rebuilt from scratch** on Laravel — a clean
re-implementation of the existing Next.js/Supabase app (which stays live until
cutover). **The authoritative plan lives in the original repo's
`Stack Change Plan.md`** (`Angel-Pappas/yolt-app`) — read it for the full context,
decisions, and roadmap.

This is a fresh, idiomatic Laravel build. **Do not port** code from the old app —
look at it only as a behavioural reference, then build clean.

## Stack (verified 2026-08-28)

- **Laravel 13** (PHP **8.4**), official **React starter kit**: React 19,
  TypeScript, **Inertia 3**, **Tailwind 4**, **shadcn/ui** (new-york, neutral,
  **Instrument Sans**, **lucide** icons, light + dark), Vite (vite-plus),
  **Fortify** auth, **Pest** tests.
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

- **Simple lookup CRUD** (Entities, Categories, VAT/Withheld rates): a controller
  with `index/store/update/destroy`, gated `can:access-finance`, sets a created-by
  `user_id` on store, uses `$request->validate(...)`, flashes a toast via
  `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])`, and
  soft-deletes on `destroy`. The frontend uses the reusable
  `resources/js/components/crud/crud-resource.tsx` (config-driven table + add/edit
  dialog + delete; text/decimal/select fields) — Categories/VAT/Withheld are thin
  config pages. Wallets/Entities are hand-written pages of the same shape (they
  predate the component); a NEW lookup should use `CrudResource`.
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

1. Read **`Stack Change Plan.md`** (repo `Angel-Pappas/yolt-app`) — its **§20
   progress log** lists exactly what's built and the next slices.
2. Pick the next slice, build it idiomatically (match the patterns above), write
   tests, run the full local verify, commit + push to `main`, watch CI green.
3. Keep each slice small and green; log the increment in the plan's §20.
