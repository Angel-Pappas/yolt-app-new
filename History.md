# History

The app's continuity record: **one entry per shipped change, newest first** — what
changed and **why** (owner decisions, trade-offs, gotchas found). Read this to see how
something came to be; read `Summary.md` for how the app works **now**.

- Add an entry for every change you ship (date, short title, what + why). Don't
  restate it in `Summary.md` — update the relevant current-state section there instead.
- Entries before 2026-10-14 were migrated from the rebuild plan's progress log and the
  old `Summary.md`; both originals are kept verbatim in `docs/archive/` (the old app's
  pre-Laravel history, the cutover runbook and the original rebuild plan live there).
- Older entries describe the app as it was then (e.g. the Business/CRM area, removed
  2026-09-14); later entries supersede earlier ones.

- **2026-10-26** — **Security fix: symfony/http-foundation 8.1.5 → 8.1.9.** Flagged
  urgent by the security watcher (CVE-2026-108082, High): any visitor, without logging
  in, could send a crafted `Cache-Control` header costing ~1 s of server CPU per request,
  slowing the whole shared VM. Every request was reachable, so this mattered more than
  the commonmark one. Patch release; only this package updated (Angel's go-ahead).
- **2026-10-26** — **Security fix: league/commonmark 2.10.0 → 2.10.4.** The new
  security watcher (`~/security-watch`) flagged CVE-2026-105830 (High): a crafted long
  Markdown text could tie up the server's PHP workers (denial of service, no data at
  risk). The library comes with Laravel; Yolt Finance never formats user-typed Markdown,
  so the real exposure was low, but the fix is a patch release with no other changes.
  The library had stayed at the version from the app's creation (2026-08-28); the other
  apps were already on 2.10.3. Only this one package was updated.
- **2026-10-23** — **Payroll bonuses.** Payroll covered only the monthly salary, so
  future cash and cost were short by about two salaries per employee per year. Each
  employee's payroll recurrence now also generates the Easter bonus, leave allowance
  and Christmas bonus (`PayrollBonuses`, wired into `RecurrenceGenerator`). Angel's
  choices: Easter shown on the Friday before Holy Monday (Orthodox Easter), Christmas
  on 20 December (each back to the previous working day), leave at the end of June;
  amounts pro rata to the days employed, and **day-weighted across every salary
  period** of the recurrence, so a raise counts for the days it covers and a moved
  end date lowers the bonuses. Pro-rata rules (8-day and 19-day steps) and the
  1,041666 factor were checked against several sources; the factor applies only
  where the leave allowance is paid inside the period, so Christmas has it and
  Easter doesn't. Leave allowance follows Angel's fraction-of-the-year rule, which
  is simpler than the statutory first-year rule (noted in Summary). The generator's
  write path was split (`writePayroll`, `baseFields`) so salaries and bonuses share
  one writer.
- **2026-10-23** — **One-offs page; open vs done on One-offs and Recurring.** Angel
  wanted one-off expenses visible on their own when reviewing the finances. A one-off
  is an ordinary transaction with a background tag (`transactions.is_one_off`, new
  column, default false — nothing existing changes); the One-offs page's **+** opens
  the usual transaction form, which sends the tag only when adding there, so edits
  from any page keep it. The page reuses the Transactions table and row actions.
  Both pages now show only what's still open: a one-off until it's reconciled; a
  recurrence while it's open-ended or its last generated transaction is unreconciled
  (`RecurringOverview::isDone`; one that ended without ever generating a row counts
  as done). **Show all** brings back the done ones in green. The toggle and the green
  row style are shared pieces (`ShowAllToggle`, `DONE_ROW_CLASS` via a new
  `rowClassName` on `DataTable`/`TransactionsTable`) so both pages match; the green
  leaves icons alone so an expense arrow never looks like income. The Recurring
  page's "Ended" badge is gone (done rows are green; the To column shows the end).
  The screenshot tool's `--click` is now repeatable.
- **2026-10-23** — **Recurring payments move off weekends and holidays.** Angel asked
  what happens when a recurrence's day is an off day: nothing did — rows landed on
  Saturdays, Sundays and holidays. His rule: contractors and payroll are paid, as is
  standard, on the **last working day of the month**; other expenses on the set day or
  the **first working day after** it. Income follows the second rule too (it isn't
  contractor/payroll). `RecurrenceGenerator` now separates the **slot** (the
  scheduled day — still the `managed_key`) from the **payment day** (the row's
  `date` and `invoice_date`), so moving a date updates the existing row instead of
  creating a second one, and reconciled rows stay frozen untouched. Reuses
  `WorkingDays` (new `nextWorkingDay`) and the stored public holidays, which is why a
  holiday write now re-syncs recurrences as well as taxes; an entity type change
  re-syncs that entity's recurrences. The form shows the rule (the "On day" field
  reads "Last working day" for contractors/employees, plus a one-line note), as does
  the entity page's cadence line. Note: live had no public holidays entered yet, so
  until they are, only weekends are skipped.
- **2026-10-23** — **Add and edit recurrences from Finance ▸ Recurring.** Angel wanted
  to manage recurrences from the Recurring page instead of going to each entity. The
  recurrence form moved to `components/recurrences/` and is now shared: opened from
  an entity's page the entity is fixed as before; opened with **+** on the Recurring
  page it starts with an entity picker (the State is left out — its payments are
  generated from the taxes). Picking the entity decides what the recurrence is
  (customer → income; employee → expense with the Payroll category; else expense)
  through one rule, `recurrenceDefaults`, now also used by the entity page, so both
  places create the same thing; after saving, the page switches to the group it
  landed in. A row click opens the same form to edit (it used to go to the entity's
  page); middle-click opens `/recurring?edit=<id>` in a new tab, like Transactions;
  each row has the same delete as the entity page (`deleteRecurrence`). Rows now carry
  the entity (id, name, type) and the full recurrence for the form.
- **2026-10-23** — **Cheese removed; new Finance ▸ Recurring page.** Angel asked to
  remove Cheese if nothing was left in it: a read-only check of the live data found no
  untyped entity (live or deleted), so the bucket is gone — its list, sidebar link,
  bulk-classify action and the "Unclassified" type option. Every entity now must have a
  type: required in validation, in the transaction form's inline "+ Add entity" (which
  used to allow none, the only way new Cheese could appear), and in the database
  (`entities.type` NOT NULL; the migration refuses to run, rather than guess, if an
  untyped entity exists). The State entity's page now links back to the tax pages
  instead of the old Cheese list. Angel also wanted one place to see every recurring
  transaction: `/recurring` lists them all, grouped **Payroll** (employee), **Contractors**,
  **Expenses** (everything else) as he asked, plus **Income** for recurring income from
  customers so it is never shown as an expense (none exist yet). Each row: entity,
  description, From/To, and the amounts of the period that matters now (in force today;
  else the next to start; else the last) — resolved by the same `AmountLines` as the
  generator, so they match the generated transactions. Ended and paused recurrences are
  still listed, marked. `pnpm run shots` gained `--click=<button>` to screenshot
  client-side tabs.
- **2026-10-15** — **Money inputs show thousands dots; faster ship.** Angel never types
  the thousands separator but wants to see it. A shared `AmountInput` now shows the
  amount Greek-style (`1.234,56`) whenever the field isn't being edited and plain
  (`1234,56`) while typing, so the dots are visual only and typing never changes
  meaning. Used for every money field: transaction lines, transfer amount, payroll,
  reconcile, wallet starting balance and the income-tax amounts (a new `money` field
  type in `CrudFormDialog`; `decimal` stays for rates in %). `lib/amounts.ts` reads
  grouped numbers too (`readAmount`; a lone dot is still the decimal) and
  `normalizeAmount` sends a plain number to the server — so the bug the unit tests had
  found (`1.234,56` read as 0) is gone. Ship got faster without changing what's
  checked: the auto-format step only formats (the full check still lints), and
  `types:check` keeps an incremental cache (`node_modules/.cache/tsc`; ~29 s → ~12 s
  locally, CI always starts fresh).
- **2026-10-15** — **One way of working across all projects; Yolt adopts the best of
  the others.** Angel asked for every project on the VM to share the same principles
  and workflow. The universal rules (quality directive, design principles, screenshots,
  Summary/History/Roadmap docs, one-command ship, live-data safety, VM etiquette,
  efficiency) now live once in `~/.claude/CLAUDE.md`, loaded by every session on the
  VM; a safety copy goes to the private repo `Angel-Pappas/ways-of-working` via
  `~/ways-of-working/sync.sh`. `Directions.md` was slimmed to Yolt-only rules. From the
  other projects Yolt took: the written **quality directive**; **`deploy.sh` refuses to
  run unless `.env` is production**, and now **migrates before building** and clears
  cached routes first (from esyksereis); a **`ROADMAP.md`**; and **frontend unit
  tests** (`pnpm run test`, vp test — in `ship.sh` and CI). Following "tests before
  refactors", the pure amount maths moved unchanged from `amount-lines.tsx` to
  `lib/amounts.ts` and got tests (plus `lib/format.ts`, `lib/search.ts`); they surfaced
  that amounts typed with a thousands dot (`1.234,56`) read as 0 — logged in the
  roadmap rather than changed silently. `ship.sh` now prints step and total timings.
  Windows-PC notes removed from `AGENTS.md`: projects are worked on through the VM only.
  Later the same day `ship.sh` was switched to the template all four web projects now
  share (auto-format → build → `composer ci:check`, i.e. exactly what CI runs).
- **2026-10-14** — **Docs restructured; one-command ship; Balance column filter.**
  Owner's aim for the docs: continuity across sessions and a way for anyone to read
  up on the app. `Summary.md` was ~240 dense lines mostly describing the retired
  Next.js app, loaded every session, while a second log (the rebuild plan's §20)
  repeated the same changes in two orders. Now: **`Summary.md` = current state only**
  (rewritten in place), **`History.md` = this log** (one entry per change, newest
  first, with the why), retired docs verbatim in `docs/archive/` (old Summary, Stack
  Change Plan incl. the cutover runbook, Taxes Plan). Missing Laravel-era entries
  (2026-09-16 taxes rebuild, 2026-09-17 TransactionsTable/categories, 2026-10-01
  wallet rule) were added from the old Summary. **`./ship.sh`** runs verify → commit →
  push → waits for that commit's CI → live `deploy.sh`, stopping at the first failure.
  The balance view's **Balance** column gained the number-range filter every other
  column has (caught by the first screenshot review).
- **2026-10-14 (process: design principles + fast screenshots).** After the duplicate
  row count, agreed with the owner: **Direction 10** writes down the design principles
  (no duplicated info, every element earns its space, review a shared component's
  whole output, fewer controls, consistency, keep user-chosen state, self-review
  before shipping) and grows each time something "weirdly made" is caught;
  **Direction 3** now has the agent look at affected pages via a fast screenshot tool
  instead of not looking at all; **Direction 11** covers loop efficiency. Tooling:
  `DemoSeeder` (fake dev data, `demo@example.com`) + `pnpm run shots <paths>`
  (`scripts/screenshot.mjs`, `playwright-core@1.63.0` on the cached Chromium 1243;
  ~12s for two pages including server start + login). Documented in `AGENTS.md`.
- **2026-10-14 (no duplicate row count).** The "N rows" line under a `DataTable` is now
  dropped whenever a totals footer is shown (the footer already carries the filtered
  count — Transactions and every `TransactionsTable`), and the table box's height cap
  reclaims that space (reserve 2.5rem instead of 4.75rem). Tables without totals keep
  the line. Deployed.
- **2026-10-14 (balance view "Starting balance" line).** Owner wants the opening
  balance when checking a wallet month by month. `WalletBalances::runningFor()` now
  also sets `balance_before` on each row (the running balance just before it), and
  `DataTable` gained an optional `leadingRow(first)` prop — a distinct summary row
  above the first data row, built from whatever row is first after the client-side
  filter/search/sort (`label` spans the columns before the first keyed cell).
  `TransactionsTable` uses it in balance view: "Starting balance" with the first
  shown row's `balance_before` under the Balance column. Pest test added. Deployed.
- **2026-10-14 (balance view survives date changes).** Changing the date range,
  presets or quick toggles while in a wallet's balance view used to drop
  `?balance=` (the filters bar rebuilt the URL without it), exiting the view.
  `TransactionsFilters` now takes the active `balanceWalletId` and keeps it in every
  filter navigation; only the balance pill's × exits the view. Deployed.
- **2026-10-14 (sticky table tops).** Owner asked for the top part of the Transactions
  list (title, search/Add, date controls, column headers) to stay put while scrolling,
  everywhere the same view is used. Done once in the shared `DataTable`: the header
  block is `sticky top-0`; the table box is its own scroll area
  (`max-h: 100svh − header height − reserve`, the header height published live as
  `--dt-head` via a `ResizeObserver`) with a sticky `<thead>` and sticky totals
  `<tfoot>` (dividers drawn as 1–2px background lines, since collapsed table borders
  don't travel with sticky rows). `Table` gained a `containerClassName` so its own
  `overflow-x-auto` wrapper can be neutralised — that wrapper would otherwise be the
  sticky ancestor. The infinite-scroll sentinel moved inside the scroll box and its
  `IntersectionObserver` uses that box as root. Full verify green; deployed.
- **2026-10-07** — **"Yolt Finance" name + Home page.** The app displays as **Yolt Finance**
  (`APP_NAME`; config/app.tsx/blade fallbacks updated too). New **Home** page at `/`
  (auth-only, logo + welcome, content later) is the post-login landing (Fortify `home`)
  and the first sidebar item; the sidebar logo links there. Removed the starter-kit
  `welcome` page. Favicon `<link>`s got a `?v=2` cache-buster so browsers drop the
  cached Laravel icon. Tests: guest → login, signed-in user sees `home`; auth redirect
  tests now expect `/`.
- **2026-10-02** — **App icon.** Replaced the Laravel starter-kit logo with a Yolt
  icon: the company's green-gradient tree-and-network mark, whose branch nodes trace a
  rising chart line with an arrow (company + finance). One artwork in three places:
  `public/favicon.svg`, `public/favicon.ico` (16/32/48) and `public/apple-touch-icon.png`
  (180, full-bleed square), plus `AppLogoIcon` (full colour, its own tile; the sidebar's
  `AppLogo` no longer wraps it in a `bg-sidebar-primary` square).
- **2026-10-01** — **Wallets-page balance = reconciled AND dated ≤ today** (owner
  decision: unreconciled rows are unverified, future rows haven't happened); the
  balance view's running walk still counts every row. Tax payments now also re-sync
  after income-tax-year and public-holiday writes. The Laravel Cloud app was deleted
  (the VM is the only host).
- **2026-09-30** — **Safe dev → live workflow on the VM.** Added a separate dev
  checkout (`/home/ploi/yolt-app-dev`, dev tools + SQLite) so work never happens in
  the live folder. `deploy.sh` is now versioned in the repo and starts with the new
  `backup-db.sh` (verified gzipped `mysqldump` to `~/backups/yolt-app/`, 60-day
  retention; the deploy aborts if it fails); the backup also runs daily from cron.
  Tests now refuse to boot on anything but in-memory SQLite (`TestCase`) and ignore a
  cached production config (`APP_CONFIG_CACHE` in `phpunit.xml`), since
  `RefreshDatabase` would otherwise wipe the live DB. Live `APP_ENV` →
  `production`. Directions/AGENTS updated (Direction 9: live data is never at risk).
- **2026-09-30** — **Database moved from Laravel Cloud to our own VM.** The live data
  was dumped from the Laravel Cloud MySQL (`production` db, `mysqldump
--single-transaction --set-gtid-purged=OFF`) and imported into the VM's local MySQL
  8.4 (`yolt_app`), replacing its empty migrated schema (a backup of the empty local
  db was taken first). Verified: every table's row count plus Σnet / ΣVAT / Σwithheld /
  Σstarting balances / max ids identical on both sides (985 transactions, 920 VAT lines,
  15 withheld lines, 87 entities, 16 categories, 4 wallets, 1 user); no pending
  migrations; `managed:sync` runs clean. APP_KEY didn't need copying (no 2FA secrets,
  no sessions). Added the missing scheduler cron on the VM. No code change.
- **2026-09-24** — **Recurring-transaction form rebuilt to match the transaction
  form; periods carry full amount lines.** The add/edit recurrence dialog now uses
  the transaction form's layout (Type income/expense · Entity — fixed, read-only ·
  Category with "+" · Wallet · Description) plus the cadence picker (Every / Unit /
  On day) and a list of **periods**. Each period = start/optional end + the **full
  transaction amount editor**: several VAT lines, the per-line **W** withholding
  toggle, and **Net/Total** (stored per period), or the payroll Net/FMY/EFKA set when
  the category is **Payroll** (payroll now follows the category, like a transaction;
  the old checkbox is gone). **"+ Add period"** copies the previous period's lines
  (rates/withholding kept, amounts blank) and starts the day after it ends; setting a
  period's start sets the previous period's end to the day before. The recurrence's
  own `start_date`/`end_date` are no longer entered — they're **derived** from the
  periods (first start → last end). **Schema:** new `recurrence_entry_lines`
  (`amount`, `vat_rate_id`, `withheld_rate_id`, `position`) + `recurrence_entries.
amount_mode`; `recurrences.vat_rate_id`/`withheld_rate_id` dropped (the migration
  folds them into one line per existing period — prod had one recurrence). **Shared
  code:** the amount resolver moved out of `TransactionController` into
  `App\Support\AmountLines` (used by the controller and `RecurrenceGenerator`, so a
  generated row is calculated exactly like a typed one, incl. Total-mode exactness);
  the frontend amount editor/summary/payroll fields moved into
  `components/transactions/amount-lines.tsx`, used by both forms. The entity is
  fixed on update (`entity_id` ignored). **253 tests; all green.**
- **2026-09-21** — **Entity types + recurring transactions + self-syncing taxes
  (the auto-created-transactions foundation).** Shipped in slices, all green.
  **Phase A:** added a nullable `type` to entities (customer/supplier/contractor/
  employee/state; null = the **Cheese** bucket); new sidebar **Entities** section
  with a per-type list each (Taxes re-homed under it as **State**); a Cheese
  classify flow (per-row + bulk); per-entity **detail pages** (`/entities/{id}`);
  and the transaction entity picker **filtered by type** (income → customers;
  expense → the payee types). **Phase B:** `recurrences` + `recurrence_entries`
  tables and `transactions.source`/`recurrence_id`/`managed_key`; a recurrence =
  fixed template + cadence (N × week/month/year on a day-of-month) + window + a
  dated amount timeline; `App\Support\RecurrenceGenerator` materialises it into
  real transactions over a rolling **24-month horizon** (immutable-date-safe,
  reconciled rows frozen); `RecurrenceController` + an on-entity editor; a daily
  `managed:sync` command (run daily by the scheduler — now from the `ploi` crontab on the VM).
  **Phase C:** `App\Support\TaxSync` turns each positive tax obligation into a
  managed **State/Taxes expense**, kept **live** (recomputed after every
  transaction/recurrence write and daily; updated/created/deleted to match; no
  feedback loop since tax rows carry zero tax fields). **`WalletBalances::all()`
  now counts only transactions dated ≤ today** (future rows are projections).
  Projections/cash-flow view deferred. **235 Pest tests** green (types, lint,
  build, pint, phpstan all clean).
- **2026-09-17** — **One shared `TransactionsTable` + table overhaul; categories
  redesign.** Every list of transactions (Transactions page, category page, tax pages)
  renders through `components/transactions/transactions-table.tsx`; lookups come once
  from the shared `financeLookups` Inertia prop. Whole row clickable (stretched link to
  `/transactions?edit=<id>`, so middle-click opens a new tab), pencil removed, ⓘ
  description tooltip + description-only edit modal, Description column removed,
  Invoice-date column added, coloured type arrows, **tri-state sorting** app-wide,
  presets This month · Last month · This year · Last year, totals footer. Categories
  page: one Add + one search over side-by-side Income/Expense lists; the add dialog's
  Type has **Both** (creates one of each); each category has its own page with an edit
  form and bulk **Move**/**Delete** of its transactions; a category can't be deleted
  while live transactions use it, and is hard-deleted once unused. The transaction
  form's Entity/Category got a **+** that opens the same add dialogs in place and
  auto-selects the new record; search is accent/tone-insensitive "contains".
- **2026-09-16** — **Taxes rebuilt on buckets & obligations; payroll taxes; income
  tax; public holidays.** Every tax is now a bucket (invoice-month, tax) that emits
  payment obligations `{amount, due_date}` (`App\Support\TaxObligation`); **all taxes
  attribute by `invoice_date`** (withholding switched from payment date) and every
  monthly tax is due on the **last working day of the following month**
  (`App\Support\WorkingDays`, weekends + a new **Public holidays** config list). VAT
  keeps credit rollover but **no longer splits debits into two installments**.
  **FMY & EFKA** captured on transactions in the category named **Payroll** (form
  morphs to Net · FMY · EFKA employee · EFKA employer; cash = net − FMY − EFKA ee;
  employer EFKA never touches the wallet). **Income tax** is a stored per-year form
  (`income_tax_years`) generating monthly installments due on that month's own last
  working day. `/taxes` opens on a per-month payment schedule above the per-tax cards;
  each tax page shows its ledger + the contributing transactions. Working plan:
  `docs/archive/Taxes Plan.md`.
- **2026-09-14 (later still)** — **Configuration area + simplified access
  control.** Moved the setup lists (Categories, VAT rates, Withheld tax) and the
  admin **Users** page out of the main nav / Settings into a dedicated
  **Configuration** area under `/configuration/*`, reached from a sidebar entry
  that opens a **tiles** landing; the sidebar is now context-aware (main nav ↔
  config list + a Back-to-app link, driven by `config-nav.ts`). **Dropped
  `can_access_finance` entirely** (migration drops the column): every active user
  now sees the whole app, and the only gate left is `admin` (guards Users). Users
  moved from `Settings\UserController` (`/settings/users`) to `UserController`
  (`/configuration/users`), admin-gated. Access model is now just
  **`is_admin` + `is_active`**. Reworked the access tests accordingly. Green:
  types, lint, build, pint, phpstan, **165 Pest tests**.
- **2026-09-14 (later)** — **Removed the Business/CRM area entirely** (it moved to a
  separate app). Deleted all CRM code (Lead/Project controllers, models, factories,
  `Support/Crm`, the `LeadLookupSeeder`, the 5 page folders, `components/crm` +
  `components/inline-edit`, 8 tests) and the **area-chooser dashboard**
  (`dashboard.tsx` + route). Dropped the `access-crm` gate, the `can_access_crm`
  users column + its grant (a **migration drops the 8 CRM tables and the column**),
  the `withCrmAccess()` factory state, and the CRM toggle in user management. The app
  is now **single-area (Finance)**: post-login home is `/transactions`, the sidebar
  shows only Finance. Verified no Finance→CRM coupling existed. All checks green
  (types, lint, pint, phpstan). Earlier the same day: rebuilt the date field as a
  segmented input and reworked withholding into a per-line toggle.
- **2026-09-14 (later)** — **Removed the LegacyImporter subsystem entirely** — the
  Supabase→MySQL cutover (§5/§7 below) ran and was verified on 2026-09-09, so the
  one-off tool is no longer needed. Deleted `App\Support\Legacy\LegacyImporter`, the
  `legacy:import` command, its test, and the `legacy` DB connection in
  `config/database.php`. (§5/§7 stay as the historical record of how the cutover was
  done.) Green: types, lint, pint, phpstan, **163 Pest tests**.
- **2026-09-10 (uniform "list view" across the app).** Every list page now shares one
  shell so the experience is identical page to page (owner's explicit direction —
  "all list views uniform… don't fix it in 50 places"). Built once in the shared
  components:
    - **`DataTable`** gained a `title` (the `<h1>` moved into the toolbar's left, with
      search + page controls + Add clustered right), an optional `controls` row, and
      `initialColumnFilters`; it registers the custom filter fns and enables faceting.
    - **Per-column header filters** — a new `data-table/column-filter.tsx` (funnel →
      shadcn `Popover`, Radix-portalled so a table's overflow never clips it) with four
      body types keyed off a column's `meta.filter`: `text` (contains), `select`
      (searchable multi-select; options given or faceted), `number` (min/max), `date`
      (from/to via `DateField`). `ColumnHeader` renders the funnel at the column's
      trailing edge. `meta` is now typed via a `ColumnMeta` module augmentation
      (`align` + `filter`).
    - **Rolled onto it:** the six `CrudResource` lookup lists (gained a `filter` per
      column); **Entities & Wallets** converted from hand-rolled `<table>`s to
      `DataTable` (so they get search + sort + header filters); **Leads & Projects**
      dropped their bespoke server filter bars — search + all column filters are now
      client-side (their `*-filters.tsx` deleted); **Taxes VAT/Withheld** gained header
      filters. **Transactions**: the toolbar was reworked to the owner's spec — the two
      date pickers far left, then **All time / This month / Last month** presets
      (This/Last month were missing), then the reconcile + missing-invoice toggles far
      right; the Type/Wallet dropdowns and the Clear button were removed (Type/Wallet/
      Category/Entity/amounts are now header filters); search moved next to Balance view
        - Add.
    - **Server contract change:** Transactions keeps only date-range, invoice-range
      (Taxes drill-down), the quick toggles, and balance view server-side; `q`/`type`/
      `wallet` dropped. Leads/Projects no longer filter server-side; **Leads still hides
      converted by default** (now unconditional — revealing converted from the list is
      gone; they live in Projects). Filter/search Pest tests updated to the new
      contract. **225 tests; all CI green; deployed.** Any new list page must be built
      from `DataTable` + `ColumnHeader` (declare `meta.filter` per column) — never a
      hand-rolled table.
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
       **RESOLVED — do not resurface:** the `LEGACY_DB_*` values are empty (no
       credentials remain); deleting the empty key names is cosmetic-only and the owner
       has chosen to leave them. Do not raise env-var cleanup as a next step again.
    - **Follow-ups — both settled, do not resurface either:** (a) the Supabase DB
      password **has been reset** (it had passed through the chat transcript) — DONE, do
      not list this as a next step again; (b) the owner **owns the "old app is
      read-only" discipline** — do not mention it again. The old Supabase DB stays intact
      as the fallback; `legacy:import` is re-runnable (delete+reloads) if ever needed.
      **The rebuild is now live with the real data.**
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
- **2026-08-31 (Finance — multi VAT lines + Net/Total toggle)** — the transaction
  amount entry is now a **list of VAT lines** (amount + rate, "+ Add VAT line" /
  remove), for the occasional single invoice mixing rates, plus one **Net/Total
  segmented toggle** for the whole transaction. The form submits `amount_mode` +
  `lines[].amount`; the server resolves each line's net/VAT from the rate (never
  trusted from the client): Net mode → VAT = net × rate; **Total mode → net = total
  ÷ (1+rate), VAT anchored to (total − net)** so a line reconstructs exactly with no
  double-rounding drift. `transactions.net`/`vat_amount` stay the authoritative
  _summed_ values (every other reader unchanged); `vat_rate_id` is the single line's
  rate or null for mixed. `vatLines` are now eager-loaded (like `withheldLines`) so
  editing seeds the real breakdown. **205 tests** (5 new: Total-mode derivation +
  exact reconstruction, multi-rate summing with null rate, single-line rate kept,
  amount_mode required; the existing transaction tests migrated to the new payload).
  All CI green; deployed. **Both areas' feature set now matches the old app** bar the
  deferred CRM polish. **Then:** the historical **import** path (Excel importer, or a
  Postgres→MySQL data migration at cutover — decide with the owner).
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
- **2026-08-31 (transactions — per-wallet balance view)** — a **Balance view**
  control on the Transactions page: picking a wallet sets `?balance=<id>` and the
  same table narrows to that wallet's history with the Wallet column swapped for a
  running **Balance** column. `WalletBalances::runningFor()` walks the wallet's
  _complete_ chronological history (seeded from `starting_balance`; income/expense
  move net+VAT−withheld, a transfer moves net on both sides) annotating each row;
  the display filters are then applied in PHP, so a filtered view still shows
  correct cumulative balances. The active search/type/date filters are preserved on
  enter/exit; the wallet filter is hidden while in balance view. **127 tests**; all
  CI green; deployed. **Still to do on Transactions:** multi VAT lines + Net/Total
  toggle, reconcile / invoice tagging — then Taxes (VAT + withholding ledgers),
  CRM, import.
- **2026-08-31 (transactions — withholding tax)** — income/expense transactions can
  now carry **Greek withholding tax** (παρακράτηση), modelled as the exact parallel
  of VAT lines: an optional withholding "line" (base + `withheld_tax_rates` rate)
  whose amount is computed server-side (`resolveWithheldLines`, never trusted from
  the client) and summed into `transactions.withheld_amount`; the cash **Total = net
    - VAT − withheld**. The form gained a Withholding base + rate pair (income/expense
      only) and a 4-column Net/VAT/Withheld/Total preview; `withheldLines` are
      eager-loaded for edit pre-fill and rewritten wholesale on save (cleared on a type
      change or when withholding is removed). `resolveLines` renamed `resolveVatLines`
      for symmetry. **123 tests**; all CI green; deployed. **Still to do on
      Transactions:** multi VAT lines + Net/Total toggle, reconcile / invoice tagging,
      per-wallet balance view — then Taxes (VAT + withholding ledgers), CRM, import.
- **2026-08-31 (transactions — filtering/search)** — the Transactions list now
  filters by free-text search (description OR entity name, debounced), type, wallet
  (matching both sides of a transfer), and a date range, all via URL query params
  read server-side in `TransactionController@index` (a `filters` prop drives the new
  `transactions-filters.tsx` toolbar; a Clear button when any is active). **118
  tests**; all CI green; deployed.
- **2026-08-31 (transactions — CRUD, transfers, balances)** — Transactions now have
  **full CRUD** (edit + delete, soft-delete; a shared validate/persist that rewrites
  the VAT lines wholesale), **transfers** (from/to wallet, no VAT; a type change on
  edit clears the other shape), and **live wallet balances** (a reusable
  `App\Support\WalletBalances` service; a Balance column on the Wallets page + shared
  Greek `formatAmount`). **114 tests**; all CI green; deployed. **Still to do on
  Transactions:** multi VAT lines + Net/Total toggle, withholding lines, reconcile /
  invoice tagging, filtering/sorting, per-wallet balance view — then Taxes, CRM,
  import.
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
- **2026-08-28 (finance reference data)** — Built the finance lookup entities:
  **Wallets** (name + starting balance), **Entities** (name + VAT number),
  **Categories** (name + income/expense type), **VAT rates** and **Withheld tax
  rates** (name + rate %) — all CRUD, `can:access-finance`-gated, soft-delete, in the
  Finance sidebar. Extracted a reusable **`CrudResource`** component (config-driven
  table + add/edit dialog + delete; text/decimal/select fields) used by
  Categories/VAT/Withheld. Schema uses **bigint PKs** (existing UUIDs remapped at the
  cutover conversion — see §5/§7). **93 tests** total; all CI green; deployed.
  built in slices.
- **2026-08-28 (user management)** — Admin **Settings ▸ Users** screen: list all
  users with inline toggles for Admin / Finance / Business / Active (self can't drop
  own admin or deactivate self — guarded server-side + disabled in the UI); the
  "Users" nav entry shows for admins only. Colleagues self-register (open
  registration for now); an admin grants access here — no SMTP needed. **58 tests**
  (5 new); all CI green; deployed. (Invite-only signup + email invites deferred
  until SMTP exists — company/later.)
- **2026-08-28 (areas + shell)** — Built the **two areas** and the **access-aware
  app shell**: route gating (`/transactions` via `can:access-finance`, `/leads` via
  `can:access-crm`), the sidebar's **Finance/Business nav groups render per access**,
  a **launcher** (dashboard) with area cards, placeholder area pages, and a
  **`user:promote {email}`** artisan command to bootstrap the first super admin.
  **53 tests** (7 new); all CI green; deployed.
- **2026-08-28 (build begins)** — First feature increment: the **access-control
  model** — four permission flags on `users`
  (`is_admin`/`can_access_finance`/`can_access_crm`/`is_active`), authorization
  **gates** (`admin`/`access-finance`/`access-crm`, each requiring active), and an
  **`EnsureAccountIsActive`** guard that logs out deactivated users. Added the new
  repo's **`AGENTS.md`** (its engineering-log/guidance doc). **7 new tests** (46
  total); all CI checks green (Pint / PHPStan / Pest / vp check / tsc); pushed +
  deployed.
- **2026-08-28 (later)** — **Deployed to Laravel Cloud.** Owner created the app
  (`production` env, Frankfurt / `eu-central-1`), attached a **MySQL 8.4** serverless
  database (Dev config — 512 MiB, sleeps when idle), and deployed. Fixed a CI
  formatting failure (`vp check --fix`) → GitHub Actions green. **App is live** at
  `yolt-app-new-production-ximjo9.laravel.cloud`; **registration + login verified
  end-to-end** (proves MySQL connected + migrations ran). **Push-to-deploy active.**
  Foundation complete.
- **2026-08-28** — **Toolchain installed** on the dev machine (verified current/
  proper versions): **PHP 8.4.24** (php.ini configured: openssl, mbstring, curl,
  fileinfo, pdo_sqlite, sqlite3, pdo_pgsql, pgsql, zip, intl, bcmath),
  **Composer 2.10.3**, **pnpm 11.24**, **Laravel installer 5.32**. **Scaffolded the
  app fresh** — **Laravel 13.29 + the official React starter kit** (React 19, TS,
  Inertia 3, Tailwind 4, shadcn/ui, vite-plus, **Fortify** auth, **Pest**), SQLite
  for local dev. **Verified:** frontend builds cleanly; **39 Pest tests pass**.
  **Pushed** to `Angel-Pappas/yolt-app-new` (`main`).
