# Summary

**How the app works today.** It's loaded every session, so it describes only the
current state: rewrite the relevant section in place when something changes, and put
the dated what/why in `History.md`. Docs map:

| File                  | What it's for                                                            |
| --------------------- | ------------------------------------------------------------------------ |
| `~/.claude/CLAUDE.md` | Shared ways of working for every project on the VM (outside the repo).   |
| `Directions.md`       | Yolt-specific standing rules.                                            |
| `Summary.md`          | Current behaviour of every area + the key decisions behind it (this).    |
| `History.md`          | One entry per shipped change, newest first: what changed and why.        |
| `ROADMAP.md`          | What's built, what's next, ideas.                                        |
| `AGENTS.md`           | How to build: stack, workflow, code patterns, checks, ship, screenshots. |
| `docs/archive/`       | Retired docs kept verbatim (old app, rebuild plan, cutover, taxes plan). |

## The app

- **Yolt Finance** (repo/project name **Yolt-App**): the company's finance app —
  transactions across wallets, entities, recurring transactions, and Greek taxes
  (VAT, withholding, FMY, EFKA, income tax) that turn into scheduled payments.
- **Laravel 13 + Inertia/React** on our own VM with MySQL; live at
  `https://yolt-app.pappas.yoltobots.click`. Built fresh on Laravel in 2026-08 to
  replace a Next.js/Supabase app; real data cut over 2026-09-09; that old app is gone.
- **Multi-user, one company.** Data is shared company data (`user_id` is only a
  created-by audit field). UI text is English; numbers/dates are Greek-style.

## Access & accounts

- Access is just **`is_active` + `is_admin`**. Every active user can use the whole
  app; admins additionally manage **Users**. A deactivated user is logged out on their
  next request (`EnsureAccountIsActive`).
- **No public sign-up.** An admin creates a user at Configuration ▸ Users and gets a
  copyable set-password link to send them (no email needed).
- **Settings** (user menu) = the user's own account only: Profile, Security
  (password, two-factor, passkeys), Appearance (light/dark/system).

## Layout & navigation

- Login lands on **Home** (`/`, logo + welcome for now).
- Sidebar: Home · **Finance** (Transactions, Wallets, Recurring, One-offs, De minimis) · **Entities**
  (Customers, Suppliers, Contractors, Employees, Shareholders, **State** = the Taxes
  area) · Configuration. Inside `/configuration/*` the sidebar
  swaps to the setup lists + "Back to app".

## Lists (every table behaves the same)

All lists render through the shared `DataTable` (rules in `AGENTS.md`):

- Header block — title, search, actions, an optional controls row, the bulk bar — is
  **sticky**; rows scroll inside a box sized to the rest of the screen, with sticky
  column headers and a sticky **totals row** where there is one.
- **Every column has a filter** (funnel: text / multi-select / number range / date
  range) and **tri-state sorting** (asc → desc → default).
- **No pagination** — 50 rows render, more load as you scroll.
- The row count shows **once**: in the totals row when the table has one, otherwise
  as a line under the table.
- Optional row selection with a bulk-actions bar; optional "leading row" above the
  data (used for the balance view's Starting balance).

## Transactions (`/transactions`)

- **Types:** Income and Expense (one wallet, entity, category, amount lines);
  Transfer (from-wallet → to-wallet, net only, no entity/VAT).
- **Two dates:** `date` (when money moved) and **`invoice_date`** (tax attribution;
  follows `date` in the form until edited by hand). Unrelated to **invoice month**
  below — the owner keeps these two strictly separate.
- **Amount lines:** one or more lines, each amount + VAT rate, with one **Net/Total**
  toggle for the whole transaction (Total mode backs VAT out so the line reconstructs
  the typed total exactly). Each line has a **W** toggle for **withholding** (base =
  the line's net, rate defaults to 20%). VAT and withholding are always computed
  **server-side** from the rate's current % (`App\Support\AmountLines`).
- **Payroll:** a transaction in the category named **Payroll** swaps the amount area
  for manual Net · FMY · EFKA employee · EFKA employer (To Pay = Net − FMY − EFKA ee;
  Total cost = Net + EFKA er).
- **Cash a transaction moves** = `net + VAT − withheld − FMY − EFKA employee`
  (employer EFKA never touches a wallet).
- **Add form:** defaults to Expense; buttons **Add**, **Add + New** (keep open,
  reset), **Add + Same** (keep open, keep everything but amounts). Entity and Category
  have a **+** to create one in place (auto-selected). The entity picker shows the
  types that fit (income → customers + shareholders; expense → suppliers, contractors,
  employees, state, shareholders) with a **Show all** toggle.
- **List:** columns Type (coloured arrow) · Date · Invoice date · Wallet · Category ·
  Entity · Net · VAT · Total · row actions. Clicking a row opens the edit dialog
  (middle-click opens it in a new tab via `?edit=<id>`). Row actions: **ⓘ**
  (description tooltip; click to edit only the description), **Reconcile**, **Invoice
  month**, Delete (soft).
- **Reconcile** opens a reduced editor (date, amount, wallet/s) and marks the row
  checked — reconciling is the record that someone verified it. Changing the amount
  rescales its VAT/withholding lines.
- **Invoice month** is a bookkeeping cue: which month's folder the paperwork went into
  (1–12), or **13 = no invoice needed** (stored as `invoice_not_required`).
- **Controls row:** from/to dates, **This month · Last month · This year · Last year**
  (default view is the current month), and two quick toggles: **unreconciled** and
  **missing invoice** (neither a month nor "not needed").
- **Balance view** (pick a wallet): only that wallet's rows, Wallet column replaced
  by a running **Balance**; a **Starting balance** row above the first shown row gives
  the balance just before it. The view stays on through date/preset/toggle changes;
  only its × exits.

## One-offs (`/one-offs`)

- One-off expenses, seen apart from the day-to-day list. A one-off is an ordinary
  transaction tagged `is_one_off`, set when it's added from this page's **+** (the
  same transaction form, `oneOff`); editing it anywhere keeps the tag. Same table,
  row click and row actions (reconcile, invoice month, delete) as Transactions.
- Only **open** (unreconciled) one-offs show; **Show all** brings back the
  reconciled ones, in green (`DONE_ROW_CLASS` + `ShowAllToggle`, shared with
  Recurring).

## De minimis (`/de-minimis`)

- The record of every **de minimis** grant, so the table grant applications ask for
  ("Πίνακας Επιχορηγήσεων de minimis") never has to be rebuilt from paper. One row per
  approval decision or contract: programme, decision no. and date, approved amount,
  paid amount, last payment date, beneficiary + VAT no. (new rows start as Yolt Labs).
  Totals row; add/edit/delete like the other lists.
- **Download** (header button) → the whole list, oldest decision first, as **Excel**
  (real numbers and dates, `1.234,56 €`) or **Markdown**, both with the official
  table's Greek headings and a Σύνολο row (`App\Support\DeMinimisExport`).

## Wallets (`/wallets`)

- Name + starting balance. The **Wallets page balance counts only rows that are
  reconciled AND dated ≤ today** (owner decision 2026-10-01: unverified or future rows
  aren't real yet). The Transactions balance view counts **every** row, so each row's
  effect shows. Balances are always computed live, never stored
  (`App\Support\WalletBalances`).

## Entities

- An entity has a name, optional VAT number and a **type**: customer, supplier,
  contractor, employee, state, shareholder (both-sided: dividends out / investment
  in). The type is **required** (validation and database) — including when an entity
  is created inline from the transaction form.
- Each type has its own list; each entity has a page (`/entities/{id}`) with an edit
  form, its transactions and its **recurrences**.

## Recurring & managed transactions

- A **recurrence** (added on an entity's page or on Finance ▸ Recurring) is a transaction template (type
  income/expense, category, wallet, description) + cadence (every N weeks/months/years
  on a day of month) + **periods**, each with its own full amount lines (or payroll
  amounts). Its start/end come from the periods.
- **Payment day** (Angel, 2026-10-23): **contractors and employees** (expenses) are
  paid on the **last working day of each month**, whatever day is set (the form shows
  "Last working day"); **anything else** on its day, or the **next working day** if
  that is a weekend or public holiday (Configuration ▸ Public holidays). A weekly
  cadence always uses its own day. Each generated row stays keyed by its scheduled
  slot (`managed_key`) and is only _dated_ on the payment day, so a moved date updates
  the same row; reconciled rows stay frozen. Adding/changing a holiday or an entity's
  type re-syncs straight away. Rule: `Recurrence::paysOnLastWorkingDay()` (client twin
  `paysOnLastWorkingDay` in `lib/recurring.ts`), dates via `App\Support\WorkingDays`.
- `App\Support\RecurrenceGenerator` turns recurrences into real transactions over a
  rolling **24-month horizon**; generated ("managed") rows are ordinary transactions
  marked with `source`/`managed_key`, kept in sync until **reconciled** (then frozen).
- **Floor date** `managed_from` (2026-09-01 in prod, `php artisan managed:from`):
  nothing managed is generated before it. `managed:sync` runs daily at 02:00.
- **Finance ▸ Recurring** (`/recurring`, `App\Support\RecurringOverview`): every
  recurrence in one list, switched by group buttons with counts — **Payroll** (entity
  is an employee), **Contractors**, **Expenses** (any other expense), **Income**. It
  opens on the first group with rows and remembers the chosen group on Back. Columns:
  Entity · Description (with a **Paused** marker) · From · To (or "Ongoing") ·
  amounts. Only **active** recurrences show: open-ended, or with an end date but its
  last generated transaction still unreconciled. **Show all** brings back the
  **done** ones (ended and last transaction reconciled), in green; the group counts
  follow what's shown. Payroll shows Net · FMY · EFKA employee · EFKA employer ·
  To pay · Total cost; Contractors Net · VAT · Withheld · Total; Expenses/Income
  Net · VAT · Total (Total = cash moved, as on Transactions). Amounts are one
  occurrence of the period in force today (else the next to start, else the last),
  resolved like the generator does. **+** adds one: the shared recurrence form opens
  with no entity; picking one sets the type (customer → income, else expense) and,
  for an employee, the Payroll category (`recurrenceDefaults`, the same rule as on an
  entity's page), and after saving the page shows the group it landed in. Clicking a
  row opens that form to edit it (middle-click: `?edit=<id>` in a new tab); the row's
  bin deletes it. Either way it is the entity's own recurrence, shown on its page too.

### Payroll bonuses

An **employee's** monthly payroll recurrence (Payroll category, entity type employee —
never contractors) also generates three bonus rows, described **Easter bonus**,
**Leave allowance** and **Christmas bonus**, with Net, FMY and EFKA ee/er all scaled
alike (an estimate; the payslip figure is set on reconciling). Their FMY/EFKA reach
the tax pages like a salary's. Rule: `App\Support\PayrollBonuses`; keys
`recurrence:{id}:bonus-{easter|leave|christmas}:{year}`, so a moved date updates the
same row; reconciled rows are frozen; same floor and horizon as salaries.

| Bonus     | Accrues        | Full amount                 | Partial                          | Paid (Angel's dates)                           |
| --------- | -------------- | --------------------------- | -------------------------------- | ---------------------------------------------- |
| Easter    | 1 Jan – 30 Apr | ½ salary                    | 1/15 of it per 8 days (÷ 120)    | Friday before Holy Monday (Orthodox Easter −9) |
| Leave     | calendar year  | ½ salary                    | days employed ÷ days in year     | last working day of June                       |
| Christmas | 1 May – 31 Dec | 1 salary × 1,041666 (25/24) | 2/25 of it per 19 days (÷ 237.5) | 20 December                                    |

- **Amount** = Σ (amount in force on each employed day of the period) × share ÷
  max(employed days, full-bonus days): a partial period is pro-rated by law, and a
  raise part-way counts for exactly the days it covers (Angel, 2026-10-23 — the law
  bases the bonus on the salary near the pay day; Angel chose day-weighting).
- **1,041666** is the leave allowance's 1/24 share; it applies only when the leave
  allowance is paid inside the bonus's period — June is inside Christmas's, not
  Easter's, so only Christmas has it.
- **Leave allowance** is Angel's rule (fraction of the year). The statutory rule for
  a first year differs: leave days accrue per month worked and the allowance is the
  pay for them capped at ½ salary, so it often reaches the full ½ after about six
  months.
- **Pay day** on a weekend or holiday → the previous working day. If the employment
  starts after it, the bonus is paid with the first salary (last working day of the
  start month); if it ends before it, with the last salary.
- Orthodox Easter: Meeus' Julian algorithm + 13 days (valid 1900–2099).
- Sources (checked 2026-10-23):
  [taxheaven — Christmas bonus](https://www.taxheaven.gr/news/65881/dwro-xristoygennwn-ypologismos-kai-katabolh-toy),
  [taxheaven — Easter table](https://www.taxheaven.gr/circulars/2981/arora-pinakas-ypologismoy-dwroy-pasxa),
  [e-forologia — Christmas](https://www.e-forologia.gr/cms/viewContents.aspx?id=212331),
  [powergame — Easter 2026](https://www.powergame.gr/ikonomia/1329130/doro-pascha-2026-poioi-to-pairnoun-olokliro-poioi-miso-pos-ypologizetai/),
  [vontikakis — bonus calculator](https://vontikakis.com/misthodosia/ypologismos-dorou?t=doro-pasxa),
  [OENET — leave allowance](https://www.oenet.gr/ergasiaka/eidikotita-ergazomenou/%CE%B5%CF%84%CE%B7%CF%83%CE%B9%CE%B1-%CE%B1%CE%B4%CE%B5%CE%B9%CE%B1-%CE%BA%CE%B1%CE%B9-%CE%B5%CF%80%CE%B9%CE%B4%CE%BF%CE%BC%CE%B1%CF%84%CE%B1-%CE%B1%CE%B4%CE%B5%CE%B9%CE%B1%CF%83),
  [RISU — Easter dates 2001–2050](https://risu.ua/en/easter-calendar-2001-2050_n33997).
  The planning note is kept in `docs/archive/Payroll bonuses plan.md`.

## Taxes — "State" (`/taxes`)

- Each tax is a **bucket** per (invoice month, tax) that emits payment **obligations**
  `{amount, due date}`. All taxes attribute by **`invoice_date`**. Monthly taxes are
  due on the **last working day of the following month** (weekends + Configuration ▸
  Public holidays excluded).
- **VAT:** output − input per month; a credit rolls forward; a debit is paid in one go.
- **Withholding, FMY, EFKA:** amounts collected in a month, paid the next month.
- **Income tax:** entered per year (`/taxes/income`); generates monthly installments,
  each due on that month's own last working day.
- `/taxes` opens on a **month-by-month payment schedule** (what's due that month),
  then a card per tax and the **tax wallet** selector; each tax page shows its ledger
  and the transactions feeding it.
- **Tax payments become transactions automatically** (`App\Support\TaxSync`): each
  positive obligation is a managed expense (entity State, category Taxes, dated on the
  due date, in the tax wallet), recomputed after every transaction/recurrence/income
  tax/holiday write and daily; deleted if the amount drops to zero.

## Configuration (`/configuration`)

- **Categories:** side-by-side Income / Expense lists with one search + one Add; Add
  can create **Both** (one of each). Each category has a page with an edit form and
  its transactions, with bulk **Move** to another category / **Delete**. A category
  can't be deleted while live transactions use it; once unused it's removed for good.
- **VAT rates**, **Withheld tax rates** (name + %), **Public holidays** (date + name).
- **Users** (admins only): create (invite link), toggle admin/active.

## Conventions

- **Money** is `decimal(12,2)`; amounts arrive as strings → `Number()` before maths.
  Displays: `1.234,56` and `dd/mm/yyyy` (`resources/js/lib/format.ts`). **Money inputs**
  (`AmountInput`) show thousands dots when not being edited (`1.234,56`) and plain text
  while typing (`1234,56`) — the dots are visual only; `,` or `.` both work as the
  decimal, and `normalizeAmount` sends a plain number to the server. Date inputs are a
  segmented dd/mm/yyyy field.
- **Soft delete** everywhere except a category removed when unused.
- **Derived values are never stored** (balances, tax figures, totals) — always
  computed live from transactions.
- **Operations:** dev checkout → `./ship.sh` (verify incl. frontend unit tests, commit,
  push, CI, deploy); `deploy.sh` only runs on the production config, backs up the DB
  first and migrates before building; daily DB backups; never test against live data
  — see `AGENTS.md`.
