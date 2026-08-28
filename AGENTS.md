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
