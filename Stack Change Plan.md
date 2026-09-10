# Stack Change Plan

Authoritative planning document for migrating **Yolt-App** from its current stack
(Next.js + Supabase, hosted on Vercel) to the company's stack (**Laravel 13 + the
official React starter kit**). It records every decision and detail agreed so far.

Keep it current, and **verify every technical fact in it against live/official
sources before relying on it** — this document is NOT exempt from that rule
(Direction 6). Each fact below is tagged **[verified 2026-08-28]**,
**[unverified — confirm at build]**, or **[DECISION NEEDED]**.

_Started: 2026-08-28. Revised: 2026-08-28 after a critical review that found ~20
gaps in the first draft (broken FKs on user migration, an infeasible
"parallel-run", schema-vs-Laravel-convention conflicts, missing views, an
overstated "100% in PHP", unverified claims, and missing prerequisites). This
revision resolves them. Status: **planning only — no build work started.**_

---

## 1. Why we are doing this (the driver)

Strategic/organizational, **not** technical dissatisfaction — the current app
works well and was just audited clean.

- The app is growing: the **Business/CRM** part means **other people in the
  company will start using it** (mostly for information; the owner is the main
  power-user).
- The company works in **PHP/Laravel**; the team objects to the different stack
  and has floated building their own app instead.
- Owner's goals, in order: (1) remove the "wrong stack" objection by moving to the
  company's stack; (2) position the app to be incorporated into company infra
  later; (3) **keep control** of the app's expansion and roadmap.
- **[DECIDED 2026-08-28] Political gate.** Once the app is on company
  infrastructure, "control" is partly *organizational*, not technical. **The owner
  will lock down a roadmap-ownership understanding before the rewrite is handed
  over.**

---

## 2. Target stack

**[verified 2026-08-28]** unless noted. Re-verify at scaffold time — versions move.

- **Laravel 13** — current major (13.10.0, 2026-08-17; released ~March 2026).
  Minimum **PHP 8.3**.
- **Official React starter kit** — `laravel new <app> --starter-kit=react`.
  Stack: **React 19, TypeScript, Inertia 3, Tailwind CSS, shadcn/ui, Vite**. Ships
  auth flows, a dashboard, and profile/password settings pages under
  `resources/js/pages/`.
- **Auth backend: Laravel Fortify** (what the starter kit uses for
  login/registration/password-reset/email-verification). A **WorkOS AuthKit**
  variant also exists (social login via an external SaaS) — **we will NOT use
  WorkOS**; it's contrary to the "own it / move onto company infra" goal. Use the
  default Fortify-based auth. **[DECISION NEEDED — confirm]** but strongly
  recommended.
- Sources: laravel.com/docs/13.x/releases, /13.x/starter-kits, /13.x/fortify;
  github.com/laravel/react-starter-kit.

Corrections the first-draft memory got wrong (why Direction 6 exists): latest
Laravel is **13** (not 12); the kit is on **Inertia 3** (not 2).

---

## 3. Architectural model (Inertia)

- **No separate API.** A Laravel controller queries the DB (Eloquent/PHP) and
  passes data as **props** to a React page component. Form submits go to Laravel
  routes (Inertia `useForm`), which run the **authoritative** logic in PHP and
  redirect back with fresh data.
- **The *authoritative* data access and business logic live in PHP.** (Corrected
  from the first draft's "100% of logic in PHP" — that was wrong. Necessary
  **client-side logic stays in React**; see §11.)
- Concept mapping (today → Laravel/Inertia):
  - Server Components → controllers feeding props.
  - Server Actions → Laravel routes + Inertia forms.
  - `revalidatePath` → Inertia's prop refresh / redirects.
  - App Router + route groups + `layout.tsx` → Laravel routes + persistent Inertia
    layouts.
  - `proxy.ts` middleware → Laravel middleware.
  - Supabase JS calls → Eloquent/DB in PHP (the Supabase client leaves the
    frontend entirely).

---

## 4. Approach — a full, from-scratch rewrite (NOT a port)

Explicit owner direction, and the guiding rule:

- **The current app is a reference/specification, not a source to copy.** Look at
  it to learn *what* to build; build each piece fresh and idiomatic in
  Laravel + Inertia + React.
- **No file ports, no copy-paste.** Nothing Next.js-shaped survives. Recreating
  the same look means writing **new Tailwind** to match (natural — the kit is
  already React 19 + TS + Tailwind).
- **Guard against the "patch it together" loop:** build each feature idiomatically
  *first*, then compare to the reference for parity — never start from pasted code
  and patch until it works.

---

## 5. Data & schema strategy

Goal: **keep all business data** and stay as idiomatic as possible.

**[DECIDED 2026-08-28] Target database engine: MySQL 8.4** — the company
standardizes on MySQL (owner ~90% sure; DB is empty so still reversible — worth a
final confirm), so we build on **MySQL**, not Postgres. This means the schema is
**rebuilt for MySQL** and the existing **Postgres (Supabase) data is migrated /
converted into MySQL** at cutover — not kept in place. That conversion is
**low-risk for this data**: money is `numeric`→`DECIMAL` (exact both sides, no
rounding), text preserved via **utf8mb4**, UUIDs → `CHAR(36)`, booleans →
`TINYINT(1)` (all handled by Laravel); the data is tiny; it's a **validated
export/import** (not a blind dump) with the **source Postgres kept intact as
fallback**; and the **parity tests re-verify every number**. See §7. _(Any
Postgres-only mechanics below — partial indexes, sequences, RLS — get a MySQL
equivalent or are already being dropped.)_

Concrete schema decisions (these fix the first draft's "keep the schema exactly" ⇄
"idiomatic Laravel" contradiction):

- **Primary keys — keep the existing UUIDs.** Every table uses UUID PKs and every
  business row's `user_id` references a user UUID. Laravel supports this via the
  **`HasUuids`** trait + `$table->uuid('id')->primary()` **[verified 2026-08-28]**.
  We reuse the **existing** UUIDs (including user IDs), so **no foreign key breaks**
  during migration. _(This was the biggest hole in the first draft.)_
- **Users — merge `auth.users` + `profiles` into one Laravel `users` table**,
  seeded with the **existing user UUIDs** and carrying the permission fields
  (`is_admin`, `can_access_finance`, `can_access_crm`, `is_active`, `full_name`,
  `email`). Passwords copy over: Supabase stores **bcrypt** (`$2a$…`), Laravel
  verifies bcrypt, so **users keep their passwords, no reset** **[verified
  2026-08-28]**. After migration the Supabase `auth.*` schema and `profiles` are
  dropped.
- **Soft deletes — use Laravel's native `SoftDeletes` keyed on `deleted_at`.** The
  existing data already sets `deleted_at` on deleted rows and leaves it null on
  active rows, so Laravel's `deleted_at IS NULL` filter maps onto the current data
  directly. The redundant `is_deleted` boolean is dropped (or ignored). **[confirm
  the data invariant holds for every table before relying on it.]**
- **Timestamps — [unverified — confirm].** The current tables have `created_at`;
  it's unconfirmed whether they have `updated_at`, which Eloquent writes by
  default. Plan: add an `updated_at` column where missing (additive, safe) or
  configure the model. Confirm per table.
- **The Postgres views** `transactions_expanded` and `wallet_balances` (workarounds
  for PostgREST's limits) are **replaced by Eloquent**, not carried over —
  ordering by joined columns is native in Eloquent, and the running/computed
  balances become PHP or SQL aggregates (as the current balance-view already does
  in JS). **[DECISION NEEDED — confirm]**: replace-with-Eloquent (recommended) vs
  keep-as-DB-views.
- **Everything else stays**: transactions, transaction_vat_lines,
  transaction_withheld_lines, entities, wallets, vat_rates, withheld_tax_rates,
  categories, leads, lead_statuses/origins/contacts/actions, projects,
  project_statuses/actions, plus the sort_order sequences.

---

## 6. Security model — RLS → application-layer authorization (its own concern)

The single **biggest correctness/security risk** of the migration, called out
explicitly (the first draft gave it two sentences).

- Today, **all** access control is enforced in the database by RLS
  (`has_finance_access()`, `has_crm_access()`, `is_admin()`, per-row `user_id`
  gates). Your own docs treat "RLS is the real lock" as sacred.
- Laravel's idiom moves this **into the application**: middleware for area access
  (finance/CRM/admin), **Policies/Gates** for per-action authorization, and
  **global query scopes** so a model can never be queried unscoped by accident.
- **This is where a bug leaks another user's data**, so the mitigations are
  first-class:
  - Enforce access in **one place per concern** (middleware + a base scoped query),
    not sprinkled per controller.
  - **Tests that *attempt* forbidden access and assert denial** (a finance-only
    user hitting CRM; user A editing user B's row; a deactivated user; a non-admin
    reassigning an action's author). These are required, not optional.
- **[DECISION NEEDED — default: no]** Optionally keep Postgres RLS as
  defense-in-depth. It fights Laravel's pooled single-DB-role connection model, so
  default is app-layer only + tests. Revisit only if we want belt-and-suspenders.

---

## 7. Migration & cutover mechanism (fixes the "parallel-run" contradiction)

The first draft's "parallel-run both apps against the shared data" is **not
feasible** — two apps with different auth systems and security models cannot both
write one live DB safely. Corrected plan:

1. **Develop against the new MySQL database** (Laravel Cloud). During the build,
   seed it with representative data; the real Postgres data is converted in at
   cutover (step 3). The old app keeps running normally on its own Postgres DB.
2. **Validate** the new app in staging (feature by feature, tests + owner
   walkthrough).
3. **One-time cutover** when ready: freeze writes on the old app briefly, take a
   final export of the Postgres data, **convert it into the new MySQL schema**
   (validated per-table export/import — e.g. `pgloader` into the clean schema, with
   row-count + value checks and the parity tests as the safety net), including the
   users merge (drop the Supabase auth artifacts), point traffic at the new app.
   **No simultaneous dual-writes.**
4. **Fallback/rollback:** keep the old app + its DB intact and runnable for an
   agreed window after cutover, so we can switch back if a blocker appears. Define
   that window before cutover.

---

## 8. Build sequence

1. **Set up the local toolchain** (see §9) — PHP 8.3+, Composer, the Laravel
   installer, Node/Vite. (This machine did not have these; it's a real first step.)
2. **Scaffold** `laravel new <app> --starter-kit=react` (Laravel 13; re-verify).
   Decide the **repo** (§9) first.
3. Stand up the project's own docs (§10) — its `AGENTS.md`/`Summary.md`/`Directions`
   equivalents; the current ones are Next/Vercel/Supabase-specific and don't carry
   over verbatim.
4. Connect to a **copy** of the Postgres schema; write Eloquent models/migrations
   to match (UUID PKs, SoftDeletes, timestamps per §5).
5. **Auth + permission model first** — Fortify auth, the merged `users` table,
   middleware + Policies + global scopes (§6), invites, set-password. With the
   denial-tests from §6.
6. **Finance area** (Transactions is the core; owner is main user), feature by
   feature — **each with tests**, each checked for **parity** (§13).
7. Then **CRM** (Leads, Projects), then **Settings**, then the **Excel import**.
8. Build to look/work/feel the same; the **real data cutover is last** (§7).

---

## 9. Repo, environment & prerequisites (new — these were missing)

- **[DECIDED 2026-08-28] Separate new repo.** The Laravel app lives in its own repo,
  **`Angel-Pappas/yolt-app-new`** (already created, currently empty). Not this repo
  — this one is wired to Vercel and auto-deploys on push to `main`. **[flag]** the
  new repo is currently **public**; recommend switching it to **private** before
  pushing the app (company financial/CRM code). Direction 2 (auto-deploy on push)
  does **not** apply to the new repo — see hosting (§17).
- **Local toolchain** to install first: PHP 8.3+, Composer, the `laravel`
  installer, Node.js (already present) for Vite. Confirm each is installed and on
  PATH before scaffolding.
- **Mail/SMTP** — invites and password resets need a real mail transport. The
  current app leaned on Supabase's (rate-limited) built-in email; Laravel needs
  **SMTP credentials** configured (`.env`). This is a prerequisite, not an
  afterthought. **[DECISION NEEDED]**: which SMTP provider.
- **Environment/secrets** — `.env` for DB connection, `APP_KEY`, mail, etc. Not
  committed. Decide where production secrets live (host's dashboard, as today).

---

## 10. Working discipline (verify & document)

- **Always assume memory is wrong and verify** — never state a version/API/"right
  way" from memory; look it up against official/current sources first. Any such
  claim made without a lookup is a bug (Direction 6).
- **Document what's verified** — the new repo gets its own engineering log
  (name/location TBD in that repo; the equivalent of this app's `Summary.md`) with
  verified facts, decisions, and source links; keep it current; consult it first,
  re-search when it's silent.
- Mirrors the existing `AGENTS.md` instinct ("This is NOT the Next.js you know —
  read the guide first"). The Laravel project needs the same posture.

---

## 11. Client-side logic to rebuild in React (new — corrects the §3 overstatement)

These are genuinely client-side and do **not** move to PHP. They're rebuilt fresh
in React and are their own parity targets:

- Live **VAT / withholding / Total preview** as the user types (a *duplicate* of
  the authoritative PHP calc — acceptable, exactly as the current app duplicates it
  between client preview and server).
- The locale-independent **segmented dd/mm/yyyy date field** (keyboard behaviour,
  clamping, ISO emit).
- **Amount inputs** — comma/dot tolerant sanitization (`,` decimal, `.` thousands).
- **Inline edits** (Next step / Status), **top-layer filter popovers**,
  **comboboxes**, **tri-state sort**, **load-as-you-scroll**, the segmented
  controls, theme switching.

(Client-side validation is for UX; the **server** re-validates authoritatively.)

---

## 12. Business logic to re-implement in PHP (each with tests)

Server-side/authoritative only (client-side items live in §11). Verified against
the current app's behaviour, encoded as tests:

- VAT lines — multi-rate, one Net/Total mode per transaction; Total-mode
  `vat_amount` anchored to `total − net` (avoids double-rounding).
- Withholding-tax lines (parallel to VAT lines; always on net; optional).
- `computeTotal = net + vat_amount − withheld_amount`.
- **Greek VAT monthly ledger** — chronological walk, credit rollover, installments
  (debit > €100 → two equal interest-free parts; ≤ €100 paid in full), gap-month
  walking; installment option always taken.
- **Withholding remittance ledger** — collected this month → payable next month,
  by **payment date**.
- **Running wallet balances** — `starting_balance` + each active transaction's
  signed effect.
- Invoice-month **1–13** resolution (13 = "no invoice needed").
- **Invoice-date** VAT-period attribution.
- **Excel import** — the fixed spreadsheet format, the 1–13 "Bacon" column, wallet
  aliases, auto-creating missing entities/categories/wallets, chunked writes,
  per-row failure reporting, upload-size handling. **[unverified — confirm]** the
  library: likely `maatwebsite/excel` or `PhpSpreadsheet`; confirm Laravel-13
  compatibility and pick at build time.
- **Soft-delete** everywhere (no hard delete in the app).
- **[DECIDED 2026-08-28]** Some Greek tax rules the current app implements are
  documented as "researched, not accountant-confirmed." **The owner will verify the
  Greek tax rules** while we rebuild them; parity otherwise means matching the
  current app.

---

## 13. Definition of "parity" / acceptance (new — was undefined)

"Looks/works/feels the same" needs teeth. A feature is **done** when:

1. Its **tests pass** (unit for logic, feature for flows + authorization denial).
2. Its **behaviour matches** the current app on a shared checklist of cases
   (including the documented edge cases — rounding, installments, soft-delete
   visibility, permission gating).
3. The **owner signs off** on look/feel from a walkthrough (the subjective part
   stays a human check, per Direction 3).

Numbers (tax, balances, VAT) are verified by **assertion against known-correct
values**, not by eyeballing.

---

## 14. Feature inventory to reproduce (parity checklist)

Build and verify one at a time — not a license to port. (Full behavioural spec:
the current app's `Summary.md`.)

- **Auth** — login, password reset, **invite flow + set-password** (public signup
  is **closed/invite-only**; there is no self-service email-verification feature to
  reproduce — corrected from the first draft).
- **Multi-user, areas & access** — permission flags (admin/finance/CRM/active), the
  two areas (Finance & Business), area switcher, launcher home, route protection,
  unified Settings.
- **App shell** — top bar, area-aware side nav, account menu, notification bell
  (placeholder).
- **Finance** — Transactions (CRUD; income/expense/transfer; VAT lines + Net/Total;
  withholding lines; reconcile; invoice 1–13 state; invoice-date; balance view;
  URL-state filters/sort, tri-state sort, per-column header filters, multi-select
  categorical filters; load-as-you-scroll; quick filters; three Add buttons; Excel
  import; default-to-current-month); Entities; Wallets (starting balance + live
  balances); Taxes (VAT ledger, withholding ledger).
- **Business (CRM)** — Leads (list with inline editors, expandable description,
  stretched-link rows, per-row add-action; edit page with campaign fields + main
  contact; History + Contacts sub-tabs); Projects (list, detail, History,
  lead→project conversion, manual "Project Agreed" vs flagged "Converted").
- **Settings** — Account, Appearance (light/dark/system), Categories, VAT rates,
  Withheld tax rates, Lead statuses, Lead origins, Project statuses, Users
  (admin/invites). _(These are the app's own settings — distinct from the starter
  kit's built-in profile/password pages, which we adapt.)_
- **Design system — [DECIDED 2026-08-28] adopt the starter kit's design language**,
  not a reproduction of the old app's visual identity. The owner prefers the clean/
  minimal starter-kit look. The new app is built **on shadcn/ui** ("new-york" style,
  **neutral OKLCH tokens**, **Instrument Sans**, **lucide** icons, light+dark built
  in — verified in the scaffold's `resources/css/app.css` + `components.json`). So
  **parity is functional (same features/behaviour), NOT a visual pixel-match** of the
  old app — the look is a deliberate upgrade. The old app's functional visual cues
  (income/expense colors; the shared table/dialog/inline-edit/action-log patterns)
  are re-expressed within shadcn components (its Table, Dialog, Sidebar, etc.). Font
  and accent color are a few token changes if we ever want to re-tint.

---

## 15. Testing philosophy (first-class)

Owner wants tests for **everything where a test makes sense** per best practices.

- **Unit tests** — isolated logic, especially **tax math** with known-correct
  expected values.
- **Feature tests** — whole flows, including **authorization denial** (§6).
- **Tooling: Pest** **[unverified — confirm which the Laravel 13 kit defaults to
  (Pest or PHPUnit); both are fine]**.
- Written **alongside** each feature. This is how "no loss of functionality"
  becomes provable and how future expansion stays regression-safe.
- Standing rule (Direction 7): after building something, assess whether it warrants
  a test per best practices; if yes, tell the owner and suggest it.

---

## 16. Managing the current live app during the build (new — was missing)

Two codebases will coexist for the whole build.

- **[DECISION NEEDED — recommended: soft feature-freeze]** on the Next app during
  the rewrite: fix bugs, but **avoid new features**, so the parity target stops
  moving. If a new feature is genuinely needed mid-build, it's added to the plan
  and built in both.
- The old app stays the source of truth until cutover (§7).

---

## 17. Hosting / infrastructure (later)

- **[DECIDED 2026-08-28] Vercel is ruled out** — it is the wrong platform for a PHP
  Laravel app (serverless/frontend-oriented; running Laravel on it is hacky). We do
  **not** set up Vercel for the new app.
- **[DECIDED 2026-08-28] Final production hosting + SMTP are the company's infra
  team's job, later**, when the app moves onto company infrastructure.
- **Preview/viewing during development** (interim, until the company hosts it):
  - **Local preview** — the app runs locally (`php artisan serve` + Vite); Claude
    previews it and shows the owner as features are built. Zero setup, immediate.
  - **A shareable URL the owner can open themselves** (the old Vercel habit) — the
    first-party equivalent is **Laravel Cloud** (deploys from GitHub, handles the
    build) **[verified 2026-08-28 it exists and deploys from GitHub]**. Connecting a
    hosting account goes **through the owner** (Claude cannot create/connect hosting
    or billing) — same division of labor as Vercel today. **[CHOSEN 2026-08-28:
    Laravel Cloud]** (~$5/mo, bundled Neon-powered Postgres). Owner created the
    account; connecting the repo is the next step (see §20).
- Preserve the **performance work**: the current DB indexes carry over (plain
  Postgres); keep them in the new migrations.
- Planned future features (recurring transactions, notifications) fit Laravel's
  **queues + scheduler** better than Vercel Cron.

---

## 18. Effort & risk

- This is a **full rewrite of a ~19k-line app with full test coverage** — realistic
  scale is **weeks-to-months**, not "a few weeks." Treat any tighter estimate as
  optimistic until the first Finance features prove a pace.
- Lower-risk than a typical rewrite because: the frontend stack **aligns**
  (React 19/TS/Tailwind); the current app is a **near-complete spec**; the owner is
  the **primary user** and validates parity; and the **one-time cutover with
  fallback** (§7) avoids big-bang data risk.
- Biggest residual risks: the **RLS→app-layer** shift (§6), **user/data migration
  correctness** (§5, §7), and **scope/parity drift** if the old app keeps changing
  (§16).

---

## 19. Open decisions (consolidated)

**Resolved 2026-08-28:**
1. **Political gate** — ✅ owner will lock down roadmap ownership before handover. (§1)
2. **Repo** — ✅ new separate repo `Angel-Pappas/yolt-app-new` (exists, empty).
   Pending: flip it **public → private**. (§9)
3. **Auth** — ✅ Fortify (skip WorkOS). (§2)
4. **Views** — ✅ replace `transactions_expanded`/`wallet_balances` with Eloquent. (§5)
5. **Feature-freeze** the old app during the build — ✅ yes. (§16)
6. **Vercel** — ✅ ruled out (wrong platform for Laravel). (§17)
7. **Final hosting + SMTP** — ✅ deferred to the company's infra team, later. (§9, §17)
8. **Accountant re-verification** of the Greek tax rules — ✅ owner will verify. (§12)
9. **Database engine** — ✅ **MySQL 8.4** (company standard; ~90% confirmed, DB empty
   so reversible). Implies a Postgres→MySQL data conversion at cutover. (§5, §7)
10. **Interim host** — ✅ **Laravel Cloud**: app deployed and **live**, MySQL 8.4 DB
    attached, **registration/login verified end-to-end**. Push-to-deploy active. (§20)

**Still open:**
- **Confirm the company's DB standard** (the ~10%) — settles the MySQL choice. (§5)
- **Repo visibility** — `yolt-app-new` is public; Laravel Cloud supports private, so
  it can be made private if desired (owner's choice — not required). (§9)
- **RLS defense-in-depth** — moot on MySQL (no RLS); authz is app-layer + tests. (§6)
- **Confirm at build:** `updated_at` columns per table (§5); Excel library +
  Laravel 13 compat (§12); type-safety for Inertia page props; **align local dev DB
  to MySQL** (scaffold used SQLite). (✅ Pest chosen; ✅ toolchain installed.)

---

## 20. Progress log

- **2026-08-28** — **Toolchain installed** on the dev machine (verified current/
  proper versions): **PHP 8.4.24** (php.ini configured: openssl, mbstring, curl,
  fileinfo, pdo_sqlite, sqlite3, pdo_pgsql, pgsql, zip, intl, bcmath),
  **Composer 2.10.3**, **pnpm 11.24**, **Laravel installer 5.32**. **Scaffolded the
  app fresh** — **Laravel 13.29 + the official React starter kit** (React 19, TS,
  Inertia 3, Tailwind 4, shadcn/ui, vite-plus, **Fortify** auth, **Pest**), SQLite
  for local dev. **Verified:** frontend builds cleanly; **39 Pest tests pass**.
  **Pushed** to `Angel-Pappas/yolt-app-new` (`main`).
- **2026-08-28 (later)** — **Deployed to Laravel Cloud.** Owner created the app
  (`production` env, Frankfurt / `eu-central-1`), attached a **MySQL 8.4** serverless
  database (Dev config — 512 MiB, sleeps when idle), and deployed. Fixed a CI
  formatting failure (`vp check --fix`) → GitHub Actions green. **App is live** at
  `yolt-app-new-production-ximjo9.laravel.cloud`; **registration + login verified
  end-to-end** (proves MySQL connected + migrations ran). **Push-to-deploy active.**
  Foundation complete.
- **2026-08-28 (build begins)** — First feature increment: the **access-control
  model** — four permission flags on `users`
  (`is_admin`/`can_access_finance`/`can_access_crm`/`is_active`), authorization
  **gates** (`admin`/`access-finance`/`access-crm`, each requiring active), and an
  **`EnsureAccountIsActive`** guard that logs out deactivated users. Added the new
  repo's **`AGENTS.md`** (its engineering-log/guidance doc). **7 new tests** (46
  total); all CI checks green (Pint / PHPStan / Pest / vp check / tsc); pushed +
  deployed.
- **2026-08-28 (areas + shell)** — Built the **two areas** and the **access-aware
  app shell**: route gating (`/transactions` via `can:access-finance`, `/leads` via
  `can:access-crm`), the sidebar's **Finance/Business nav groups render per access**,
  a **launcher** (dashboard) with area cards, placeholder area pages, and a
  **`user:promote {email}`** artisan command to bootstrap the first super admin.
  **53 tests** (7 new); all CI green; deployed.
- **2026-08-28 (user management)** — Admin **Settings ▸ Users** screen: list all
  users with inline toggles for Admin / Finance / Business / Active (self can't drop
  own admin or deactivate self — guarded server-side + disabled in the UI); the
  "Users" nav entry shows for admins only. Colleagues self-register (open
  registration for now); an admin grants access here — no SMTP needed. **58 tests**
  (5 new); all CI green; deployed. (Invite-only signup + email invites deferred
  until SMTP exists — company/later.)
- **2026-08-28 (finance reference data)** — Built the finance lookup entities:
  **Wallets** (name + starting balance), **Entities** (name + VAT number),
  **Categories** (name + income/expense type), **VAT rates** and **Withheld tax
  rates** (name + rate %) — all CRUD, `can:access-finance`-gated, soft-delete, in the
  Finance sidebar. Extracted a reusable **`CrudResource`** component (config-driven
  table + add/edit dialog + delete; text/decimal/select fields) used by
  Categories/VAT/Withheld. Schema uses **bigint PKs** (existing UUIDs remapped at the
  cutover conversion — see §5/§7). **93 tests** total; all CI green; deployed.
  built in slices.
- **2026-08-28 (transactions — first slices)** — **Data model**: transactions +
  transaction_vat_lines + transaction_withheld_lines tables + models with
  relationships (bigint PKs). **List view**: `TransactionController@index` + a table
  (Type/Date/Wallet/Category/Entity/Description/Net/VAT/Total); shared Greek-style
  `resources/js/lib/format.ts` (formatAmount 1.234,56; formatDate dd/mm/yyyy).
  **Entry form (income/expense)**: `store` computes each VAT line's amount from the
  rate's current % **server-side** (never trusted from the client), sums net/vat,
  writes the transaction + lines in a DB transaction; a dialog with type/date/
  invoice-date/entity/category (filtered by type)/wallet/net/VAT-rate/description +
  a live Net/VAT/Total preview. **104 tests**; all CI green; deployed. **Still to do
  on Transactions:** transfers, multi VAT lines + Net/Total toggle, withholding
  lines, edit/delete, reconcile, invoice-month, filtering/sorting, balance view —
  then Taxes (VAT + withholding ledgers), the Business/CRM area, and the import.
- **2026-08-31 (transactions — CRUD, transfers, balances)** — Transactions now have
  **full CRUD** (edit + delete, soft-delete; a shared validate/persist that rewrites
  the VAT lines wholesale), **transfers** (from/to wallet, no VAT; a type change on
  edit clears the other shape), and **live wallet balances** (a reusable
  `App\Support\WalletBalances` service; a Balance column on the Wallets page + shared
  Greek `formatAmount`). **114 tests**; all CI green; deployed. **Still to do on
  Transactions:** multi VAT lines + Net/Total toggle, withholding lines, reconcile /
  invoice tagging, filtering/sorting, per-wallet balance view — then Taxes, CRM,
  import.
- **2026-08-31 (transactions — filtering/search)** — the Transactions list now
  filters by free-text search (description OR entity name, debounced), type, wallet
  (matching both sides of a transfer), and a date range, all via URL query params
  read server-side in `TransactionController@index` (a `filters` prop drives the new
  `transactions-filters.tsx` toolbar; a Clear button when any is active). **118
  tests**; all CI green; deployed.
- **2026-08-31 (transactions — withholding tax)** — income/expense transactions can
  now carry **Greek withholding tax** (παρακράτηση), modelled as the exact parallel
  of VAT lines: an optional withholding "line" (base + `withheld_tax_rates` rate)
  whose amount is computed server-side (`resolveWithheldLines`, never trusted from
  the client) and summed into `transactions.withheld_amount`; the cash **Total = net
  + VAT − withheld**. The form gained a Withholding base + rate pair (income/expense
  only) and a 4-column Net/VAT/Withheld/Total preview; `withheldLines` are
  eager-loaded for edit pre-fill and rewritten wholesale on save (cleared on a type
  change or when withholding is removed). `resolveLines` renamed `resolveVatLines`
  for symmetry. **123 tests**; all CI green; deployed. **Still to do on
  Transactions:** multi VAT lines + Net/Total toggle, reconcile / invoice tagging,
  per-wallet balance view — then Taxes (VAT + withholding ledgers), CRM, import.
- **2026-08-31 (transactions — per-wallet balance view)** — a **Balance view**
  control on the Transactions page: picking a wallet sets `?balance=<id>` and the
  same table narrows to that wallet's history with the Wallet column swapped for a
  running **Balance** column. `WalletBalances::runningFor()` walks the wallet's
  *complete* chronological history (seeded from `starting_balance`; income/expense
  move net+VAT−withheld, a transfer moves net on both sides) annotating each row;
  the display filters are then applied in PHP, so a filtered view still shows
  correct cumulative balances. The active search/type/date filters are preserved on
  enter/exit; the wallet filter is hidden while in balance view. **127 tests**; all
  CI green; deployed. **Still to do on Transactions:** multi VAT lines + Net/Total
  toggle, reconcile / invoice tagging — then Taxes (VAT + withholding ledgers),
  CRM, import.
- **2026-08-31 (Taxes area — VAT + withholding ledgers)** — the **Taxes** section
  (side-nav link, finance-gated): a `/taxes` index with a card per tax type showing
  the current period's headline figures, and a full month-by-month page each.
  **`VatLedger`** walks the complete history chronologically (by `invoice_date`),
  threading a **credit rollover** (negative net carries forward indefinitely) and
  **two-installment** deferral (a debit over €100 splits half-now/half-next-month,
  ≤€100 paid in full) — gap months emitted so state passes through them; columns
  Month / Income VAT / Expenses VAT / Net / Roll over / Payable this month / Payable
  next month. **`WithheldLedger`** is the simpler parallel (by payment `date`,
  expense-side only): collected this month → payable next. Both are derived live,
  never stored. **Tests caught a real bug** — `Carbon::createFromFormat('Y-m', …)`
  inherits today's day-of-month and overflows a short month (Feb on the 31st → Mar);
  fixed by anchoring to `-01`. **141 tests** (12 new ledger tests covering rollover,
  installments, gap months, payment-date attribution); all CI green; deployed.
  **Deferred:** month→transactions drill-down links (needs an `invoice_date` filter
  on the transactions list, not yet built). **Still to do on Transactions:** multi
  VAT lines + Net/Total toggle, reconcile / invoice tagging — then CRM, import.
- **2026-08-31 (Business/CRM — lead statuses & origins lookups)** — first CRM slice,
  the foundation Leads will reference. Two `can:access-crm`-gated lookup lists —
  **`lead_statuses`** (New/Contacted/Follow-up/Proposal/Won/Lost) and
  **`lead_origins`** (Campaign/Ads/Expo/Referral/Website) — each `name` + `position`
  (a new status/origin appends at max+1), built on the existing `CrudResource`
  pattern (mirrors VAT rates), with side-nav links under Business. An idempotent
  **`LeadLookupSeeder`** (firstOrCreate, safe to run against prod) seeds the
  defaults. **148 tests** (7 new: CRUD, position append, access gating, seeder
  idempotency); all CI green; deployed. **Next CRM slices:** Leads list + CRUD, then
  lead detail (contacts + activity log), Projects, and lead→project conversion.
- **2026-08-31 (Business/CRM — Leads list + CRUD)** — the core Leads feature: a
  `leads` table (auto **No.** `sort_order` assigned at `withTrashed()->max+1` so a
  soft-deleted lead keeps its number — **never reused**; a test caught that the
  default soft-delete scope would otherwise reset it), origin/status FKs to the
  lookups, a lead-level website, and a **main contact** (`contact_*` columns:
  name/position/email/phone/landline), description, next step. A list page (No. /
  Origin / Name / Email / Phone / Next step / Status + edit/delete) with a
  search+origin+status filter bar, and a full add/edit dialog (name, origin/status
  selects, main-contact fieldset, description/next-step textareas — added a shadcn
  `Textarea` component). **155 tests** (7 new: CRUD, auto-No. append, never-reused,
  validation, filter/search, access gating); all CI green; deployed. **Deferred to
  later CRM slices:** additional contacts + activity-log (History) sub-tabs on a
  lead detail page, campaign-only fields (platform/we-are/we-want), inline
  next-step/status editing, phone formatting — then Projects and lead→project
  conversion.
- **2026-08-31 (Business/CRM — lead detail page + activity log)** — a `/leads/{lead}`
  detail page (lead name links to it from the list): a header with the auto No., the
  full lead info card, an "Edit lead" button reusing the `LeadFormDialog`, and a
  **History** log. `lead_actions` (nested under a lead, `cascadeOnDelete`) has an
  editable **`action_date`** (defaults today, separate from the `created_at` audit
  stamp) and a denormalized **`author_name`** (a colleague's name can't be joined
  under the CRM's shared-read model; author = the acting user). `LeadActionController`
  store/update/destroy guard that the action belongs to the lead in the URL (404
  otherwise); an `ActionFormDialog` (date + body) drives add/edit. **161 tests** (6
  new: detail render, log/attribution, validation, update/delete, wrong-lead 404,
  access gating); all CI green; deployed. **Deferred:** admin actor-picker (attribute
  an action to another user), the Contacts sub-list, inline next-step/status editing,
  campaign fields — then Projects and lead→project conversion.
- **2026-08-31 (Business/CRM — additional lead contacts)** — a `lead_contacts` table
  (nested under a lead, `cascadeOnDelete`, soft-delete) for people beyond the main
  contact: name/position/phone/landline/website/email. `LeadContactController`
  store/update/destroy with the same wrong-lead 404 guard as actions; a
  `ContactFormDialog` drives add/edit; the lead detail page renders a **Contacts**
  card (Name / Position / Phone / Email + edit/delete) below History. **167 tests**
  (6 new); all CI green; deployed. The lead detail page is now feature-complete bar
  the deferred polish. **Deferred:** admin actor-picker, inline next-step/status
  editing, campaign fields, phone formatting — then Projects and lead→project
  conversion.
- **2026-08-31 (Business/CRM — Projects foundation)** — the second Business section.
  A `project_statuses` lookup (Agreed/Scoping/Contracting/In progress/Delivered/
  Cancelled, its own pipeline — now seeded by the renamed-in-spirit `LeadLookupSeeder`
  alongside the lead lookups) and a `projects` table: auto **No.** (`sort_order`,
  withTrashed max+1, never reused), a nullable `lead_id` (the originating lead,
  `nullOnDelete` — a project holds project info only and links back rather than
  copying the lead), status FK, `value` (decimal, a manual amount for now — a later
  phase sums it from deliverables), `estimated_months`, description, next step. A
  projects list (No. / Name / **Client** = the linked lead's main-contact name read
  live / Status / Value / Next step + edit/delete) with search+status filters, an
  add/edit dialog, and Business side-nav links (Projects, Project statuses). **179
  tests** (16 new across project + project-status CRUD, auto-No./never-reused,
  client surfacing, validation, filters, access gating, seeder). All CI green;
  deployed. **Next CRM slices:** project detail page + History (`project_actions`),
  then the lead→project **conversion** (Convert button, the "Converted"/"Project
  Agreed" done-states, hiding converted leads).
- **2026-08-31 (Business/CRM — project detail + History, shared action log)** — a
  `/projects/{project}` detail page (linked from the list): header with No., the
  linked lead's contact name and a **"View lead →"** link, an info card
  (status/value/estimated-months/description/next-step), an Edit button reusing the
  project form dialog, and a **History** log. `project_actions` (the exact parallel
  of `lead_actions`, `cascadeOnDelete`, editable `action_date`, denormalized
  `author_name`); `ProjectActionController` with the wrong-project 404 guard. The
  History entry dialog was **extracted to a shared `components/crm/action-form-dialog`**
  (takes a `baseUrl` prop) and **Leads migrated onto it** (the leads-local copy
  deleted) — first shared CRM component, per the plan's shared-action-log intent.
  **185 tests** (6 new); all CI green; deployed. **Next CRM slice:** the lead→project
  **conversion** (Convert button, "Converted"/"Project Agreed" done-states, hiding
  converted leads).
- **2026-08-31 (Business/CRM — lead→project conversion)** — ties Leads and Projects
  together. `lead_statuses` gained an **`is_conversion`** flag; the seeder adds the
  two done-states — **"Project Agreed"** (a normal manual stage a salesperson picks)
  and the flagged **"Converted"** (set by the Convert action). Logic keys off the
  flag, never the name. (MySQL has no partial unique index for single-true, so the
  invariant is by construction: the flag is data-only, not editable via the status
  CRUD.) `ProjectController@convert` (POST `/leads/{lead}/convert`) creates a project
  linked to the lead, flips the lead to Converted, and redirects to the project — in
  a DB transaction; a lead converts **only once** (an existing project redirects
  instead of duplicating). The leads list **hides converted leads by default**
  (excludes the conversion status unless the status filter asks for it — with the
  `is null OR != ` guard so null-status rows survive); the status filter still lists
  Converted. The lead form's status dropdown **omits the conversion status** unless
  it's the lead's current value (so saving doesn't silently clear it). The lead
  detail page shows **"Convert to project"** (a name-prompt dialog, pre-filled with
  the lead name) or **"View project →"** once converted. **192 tests** (7 new); all
  CI green; deployed. **The Business/CRM area (Phase 1) is now functionally
  complete.** **Deferred CRM polish:** admin actor-picker, inline next-step/status
  editing, campaign fields, phone formatting, History/Contacts as tabs. **Remaining
  Finance polish:** multi VAT lines + Net/Total toggle, reconcile/invoice tagging.
  **Then:** the historical **import** tool.
- **2026-08-31 (Finance — reconcile + invoice tagging)** — the transaction row's
  bookkeeping cues (columns already existed on `transactions`). A **reconcile**
  toggle button per row (POST `/transactions/{t}/reconcile`, flips `is_reconciled`,
  lit green when on) and an **invoice** button opening a one-field dialog: a single
  1–13 input files the transaction under that month's folder (1–12 → `invoice_month`,
  13 → `invoice_not_required`, blank → clears both), so the rest of the app never
  sees the "13" convention. Two **quick-filter toggles** in the transactions toolbar
  — **unreconciled** (`is_reconciled = false`) and **missing invoice** (`invoice_month`
  null AND not not-required, i.e. "not yet worked on") — wired into both the DB list
  path and the balance-view PHP path. **200 tests** (8 new: toggle, the three
  invoice states, out-of-range rejection, both quick filters, access gating); all CI
  green; deployed. **Remaining Finance polish:** multi VAT lines + Net/Total toggle.
  **Then:** the historical **import** tool (or a Postgres→MySQL data migration at
  cutover — decide with the owner which path).
- **2026-08-31 (Finance — multi VAT lines + Net/Total toggle)** — the transaction
  amount entry is now a **list of VAT lines** (amount + rate, "+ Add VAT line" /
  remove), for the occasional single invoice mixing rates, plus one **Net/Total
  segmented toggle** for the whole transaction. The form submits `amount_mode` +
  `lines[].amount`; the server resolves each line's net/VAT from the rate (never
  trusted from the client): Net mode → VAT = net × rate; **Total mode → net = total
  ÷ (1+rate), VAT anchored to (total − net)** so a line reconstructs exactly with no
  double-rounding drift. `transactions.net`/`vat_amount` stay the authoritative
  *summed* values (every other reader unchanged); `vat_rate_id` is the single line's
  rate or null for mixed. `vatLines` are now eager-loaded (like `withheldLines`) so
  editing seeds the real breakdown. **205 tests** (5 new: Total-mode derivation +
  exact reconstruction, multi-rate summing with null rate, single-line rate kept,
  amount_mode required; the existing transaction tests migrated to the new payload).
  All CI green; deployed. **Both areas' feature set now matches the old app** bar the
  deferred CRM polish. **Then:** the historical **import** path (Excel importer, or a
  Postgres→MySQL data migration at cutover — decide with the owner).
- **2026-08-31 (cutover — legacy data migration).** Owner's decision: **migrate the
  live old DB into the new one** (not re-import Excel — the Excel importer is retired
  and must NOT be added). Every row moves to its correct place so nothing is lost or
  changes when the owner switches apps. Two parts:
  1. **Campaign fields on leads** (`campaign_platform` enum fb/ig, `campaign_we_are`,
     `campaign_we_want`) — the one schema gap vs. the old `leads`. Shown in the lead
     form only when origin is "Campaign", cleared otherwise. **Required**: all 58 old
     leads carry campaign data, so without these every lead would lose it.
  2. **`legacy:import` Artisan command** + **`App\Support\Legacy\LegacyImporter`**.
     Reads a `legacy` Postgres connection (config/database.php, env `LEGACY_DB_*`),
     replaces the finance+CRM data with a faithful copy of the old app's: loaded
     parents-first with **UUID→bigint id remapping** (an id-map per table that FKs
     translate through), **soft-delete preserved** (old `is_deleted`+`deleted_at` →
     new `deleted_at`, so deleted rows stay deleted), **`created_at`/`sort_order`
     preserved**, and **created-by remapped** to the matching new user by email
     (users themselves are NOT migrated — the owner's login stays; passwords can't
     move). `delete()` not `truncate()` (TRUNCATE implicitly commits on MySQL) keeps
     it atomic in one transaction. The importer is reader-driven so it's unit-tested
     against a fixture (**9 tests**: FK remap, transfers, soft-deletes, campaign
     fields, user-by-email mapping, full-replace, pretend mode). `--pretend` reports
     source counts without writing; the real run confirms first (`--force` skips).
     **216 tests total; all CI green; deployed.**
     - **Verified against the real old DB** (via Supabase MCP): 909 transactions (3
       soft-deleted), 906 VAT lines, 100 entities, 34 categories, 3 wallets, 4 VAT
       rates, 58 leads (all with campaign data), 25 lead actions, 1 lead contact, 7
       lead statuses (one `is_conversion`="Converted"), 5 origins, 6 project statuses,
       projects/project_actions empty, 1 withheld line. Single owner
       (`a.pappas@yoltlabs.com`); no null wallets; no multi-rate transactions. Every
       column maps cleanly; nothing in the old schema is unhandled.

### Cutover runbook (run once, when switching to the new app)

The migration **replaces** the new app's finance+CRM data with the old app's, so run
it at the moment of cutover. It runs wherever both databases are reachable and
`pdo_pgsql` is present — **Laravel Cloud is the natural place** (it hosts the new
MySQL and can reach Supabase over the internet).

1. **Get the old DB connection** — Supabase dashboard → old project (`yolt-app`,
   ref `mzfxfweljbfvyqlhvmzr`) → Project Settings → Database → connection info (host,
   port, database `postgres`, user, password; the session pooler is fine).
2. **Set env on the new app** (Laravel Cloud → Environment): `LEGACY_DB_HOST`,
   `LEGACY_DB_PORT`, `LEGACY_DB_DATABASE`, `LEGACY_DB_USERNAME`, `LEGACY_DB_PASSWORD`,
   `LEGACY_DB_SSLMODE=require`. Redeploy so config picks them up.
3. **Dry run**: `php artisan legacy:import --pretend` → confirms connectivity and
   prints the source row counts (should match the figures above — transactions 909,
   etc.).
4. **Run it**: `php artisan legacy:import` (confirm the prompt, or `--force`). Prints
   the migrated-row counts.
5. **Verify** in the app; then remove the `LEGACY_DB_*` env vars.
   - Re-runnable: it deletes+reloads, so a second run just refreshes from the old DB.
   - The owner's existing new-app **login keeps working** (users aren't touched); all
     migrated rows are attributed to that user by email.

- **2026-08-31 (parity pass — closing deferred gaps).** After the owner asked for a
  full audit, the old app's `Summary.md` was checked feature-by-feature against the
  new code. ~17 simplifications/gaps were found (several I'd deferred without clearly
  surfacing). The owner directed: close them all, and prefer ready-made libraries
  over hand-rolling. Done so far (each its own commit, CI green, deployed):
  1. **Invite-only** — Fortify registration disabled, register page/links removed;
     a new **admin invite flow** (Settings ▸ Users): create user + access flags →
     password-broker token → copyable set-password link (no email dependency; 3-day
     token). Invitee uses the existing reset-password page.
  2. **Rich tables via TanStack Table v8** (pinned — v9 just released with a reworked
     `useTable`/`tableFeatures` API, too green to build on). A shared **`DataTable`**
     (`components/data-table/`, on the shadcn Table primitives) with sortable headers
     (`ColumnHeader`), global search, and client pagination. Every list renders
     through it: the lookup lists (via `CrudResource`) gain search+sort+pagination
     (the 100-entity list is searchable); Leads/Projects/Transactions gain sortable
     headers + pagination while keeping their server-side filter toolbars. Removes
     the render-all-900-rows concern.
  3. **Searchable Combobox** (shadcn Popover + cmdk) for the transaction form's
     Entity/Category — type-to-filter instead of a 100-item dropdown.
  4. **Current-month default** on Transactions (redirect to this month's range; an
     "All time" toggle / `all=1` opts out; explicit ranges too).
  5. **Taxes month drill-down** — VAT months link to their transactions by a new
     `invoice_from`/`invoice_to` filter (VAT is by invoice date), withholding months
     by payment date + `type=expense`.
  6. **Inline next-step/status editing** in the Leads/Projects lists (reusable
     `EditableNextStep`/`EditableStatus` + lightweight PATCH endpoints; the lead
     status editor hides the conversion status).
  7. **Phone formatting** (`formatPhone`, grouped display) and **invoice-date
     follows the transaction date** until edited.
  - **The rest, now also done** (the owner directed all of them in — my
    low-value framing was wrong; notably the owner's browser runs in **English**, so
    the native date input showed American mm/dd/yyyy, making the custom field a real
    fix): **Add+New/Add+Same** (batch entry); the reduced-edit **reconcile modal**
    (date/amount/wallet + reconciled flag; changing an income/expense amount rescales
    its VAT lines proportionally and re-derives VAT); **multi-line withholding** (the
    form now produces the array the backend already summed); a locale-independent
    **dd/mm/yyyy `DateField`** (auto-mask + react-day-picker calendar, emits ISO) on
    the transaction/reconcile/History date fields and the transaction filters;
    **History/Contacts tabs** on the lead detail (shadcn Tabs); the **Taxes ledgers
    on the shared DataTable**; and the **admin actor-picker** on activity logs
    (`Crm::resolveActor` — non-admins are always themselves). **All 17 audit items
    closed; 229 tests; CI green; deployed.** New deps: `@tanstack/react-table@^8`,
    `@radix-ui/react-popover`, `cmdk`, `react-day-picker`, `date-fns`,
    `@radix-ui/react-tabs`. **The rebuild is feature-complete against the old app;
    the only remaining step is the cutover data migration** (`legacy:import` — see
    the runbook above).
- **2026-09-09 (CUTOVER EXECUTED — the data migration ran, verified clean).** The
  owner chose a full cutover. Ran the runbook against production on Laravel Cloud:
  1. Confirmed the runtime can read Postgres (`pdo_pgsql` present, PHP 8.5.10).
  2. Set `LEGACY_DB_*` on the production env pointing at the **direct** Supabase host
     `db.mzfxfweljbfvyqlhvmzr.supabase.co:5432` (user `postgres`, db `postgres`,
     `sslmode=require`) — the direct host connected fine from Laravel Cloud, no pooler
     needed. Redeployed so config picked them up.
  3. `legacy:import --pretend` → source counts matched a fresh MCP baseline exactly.
  4. `legacy:import --force` → **succeeded** (exit 0, ~11s). Migrated: transactions
     916, transaction_vat_lines 913, transaction_withheld_lines 3, entities 100,
     categories 34, wallets 3, vat_rates 4, withheld_tax_rates 1, leads 58,
     lead_actions 25, lead_contacts 1, lead_statuses 7, lead_origins 5,
     project_statuses 6, projects/project_actions 0.
  5. **Verified in the new MySQL DB (not just row counts):** net Σ **810838.25**, VAT Σ
     **66779.33**, withheld Σ **981.16**, transfers 3, reconciled 249, invoice-month
     set 439, soft-deleted 3 — all match the old DB to the cent. Wallet balances via
     the new app's own `WalletBalances` service: Alpha Bank **11347.61**, Register
     **5209.78**, Cash **0.00** — all match. Zero orphaned VAT/withheld lines.
  6. **Cleanup:** blanked the `LEGACY_DB_*` values on Laravel Cloud (no delete-var MCP
     action, so set to empty; keys can be removed in the dashboard). The owner's
     new-app login is untouched (users not migrated; rows attributed by email).
  - **Follow-ups for the owner:** (a) **reset the Supabase DB password** — it passed
    through the chat transcript (resetting doesn't disrupt the old app, which uses API
    keys); (b) treat the **old app as read-only** now — further writes there won't
    reach the new app unless `legacy:import` is re-run (it's re-runnable: it
    delete+reloads). The old Supabase DB stays intact as the fallback. **The rebuild
    is now live with the real data.**
