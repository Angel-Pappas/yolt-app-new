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
  transactions across wallets, entities, recurring transactions, projects and
  planned income, agreements, payroll, and Greek taxes (VAT, withholding, FMY, EFKA,
  income tax) that turn into scheduled payments — all feeding a cash-flow projection.
- **Transactions drive everything.** Past rows are the ledger (reconciled = checked);
  future rows are the plan. Recurrences, projects and taxes only _produce_
  transactions; every balance, tax and projection is computed from transactions.
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

- Login lands on **Home** (`/`): a dashboard of what needs attention — money really
  in the wallets, customer payments due this month and owed from earlier months,
  taxes to pay this month, planned items **not matched yet**, the year ahead (graph +
  lowest balance), agreements ending soon. Every figure links to its page.
- Sidebar (Angel, 2026-11-19), split by purpose: Home · **Finance** — what is
  discussed with the CEO: **Planning**, **Projection**, **Projects** · **Accounting**
  — the books, kept monthly: **Transactions**, **Bank import** (a **!** while planned
  items wait unmatched), **Wallets**, **Entities**, **Agreements**, **De minimis**,
  **Configuration**. Inside `/configuration/*` the sidebar swaps to the setup lists +
  "Back to app".
- **Tabbed pages** show their tabs where a list's title goes (`PageTabs`, names only,
  no counts anywhere): **Planning** = Income · Recurring · One-offs · Taxes
  (`/planning/{tab}`; the old `/income`, `/recurring`, `/one-offs`, `/taxes/…`
  redirect); **Entities** = Customers · Suppliers · Contractors · Employees ·
  Shareholders · State (`/entities/{type}`). Each tab is its own address; the sidebar
  link reopens the tab last used. A customer's page has its own tabs (Payments ·
  Projects · Recurring income · Agreements · Transactions; `?tab=` deep-links).
- Phone: decent, not polished — tabs wrap, wide tables scroll inside their box; a
  proper mobile pass is on the roadmap.

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
- **A list remembers its setup** — search, column filters, sorting, scroll, and
  toggles like "Show all" or the Recurring group — for the browser tab's session
  (`lib/stored-state.ts`, `useStoredState`, the table's `stateKey`), so it is the
  same when you come back from an item's page.
- **Every row that opens something is a real link** (`rowHref`): middle/Ctrl-click
  opens it in a new tab — entities, projects, rates, categories, wallets (→ their
  transactions with running balance), recurrences, transactions, projection months.

## Counting or not: the on/off switch

- Any item can be **switched off** (the eye button): it stays in its list, struck
  through, and **counts for nothing** — balances, the projection, every tax. This is
  how "what if" is tried. The switch is on a **transaction**, a **recurrence** (all
  its occurrences), a **planned one-off** and a **customer payment line**. A
  **reconciled** transaction always counts; a tax payment can't be switched.
- One rule for real rows: `Transaction::scopeCounted()` / `counts()` (client twin
  `transactionCounts`). Old rows tagged as a dropped income (from before 2026-11-19)
  still count for nothing; the tag is no longer offered.

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
- **Transactions is history** (Angel, 2026-11-19): only what really happened. The
  future lives in the plan (see **The plan beside the books**). A date after today
  is still allowed, with a note that something expected belongs in Planning; editing
  a row dated in a wallet's concluded period says so (allowed).
- **Pays planned item:** a hand-typed row can say which open planned item it pays
  (the entity's items, same direction); picking the entity of a new row preselects
  the one item it most likely pays (closest within 45 days, if only one). The row
  then carries the item's key (`managed_key`) and the item is paid by that much.
- **Add form:** defaults to Expense; buttons **Add**, **Add + New** (keep open,
  reset), **Add + Same** (keep open, keep everything but amounts). Entity and Category
  have a **+** to create one in place (auto-selected). The entity picker shows the
  types that fit (income → customers + shareholders; expense → suppliers, contractors,
  employees, state, shareholders) with a **Show all** toggle. On a wallet that takes
  bank fees (Wallets ▸ "Bank fees go to"), a **Bank fee** field (quick picks = the
  wallet's most frequent fees in the last year) adds the fee as its own expense to
  that entity and category, same day.
- **Order within a day** follows the bank: rows a statement line confirmed sit in the
  statement's order (`bank_position`), then the rest in the order they were added
  (`Transaction::scopeInBankOrder()`, used by every list and the balance view).
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

## The plan beside the books (Angel, 2026-11-19)

- **Real** = the transactions that happened. **The plan** = everything still
  expected (`App\Support\Plan`, one shape: `PlanItem`):
    - **recurrence occurrences** (salaries and bonuses included), **computed live**,
      never stored — key `recurrence:{id}:{slot}` (bonuses `…:bonus-…`);
    - **tax payments**, computed live from the tax ledgers over the books and the rest
      of the plan — key `tax:{tax}:{period}`;
    - **planned one-offs** and **customer payment lines** — kept as **planned rows** of
      the `transactions` table (`PlannedTransaction`, `is_planned`), hidden from every
      real query by a global scope — key `planned:{id}`.
- **Paid by key:** a real row carrying an item's key (`managed_key`) pays it. A
  planned row (one-off, payment line) stays open for what is left (**open = its cash
  − what real rows with its key moved**). A computed item (recurrence occurrence,
  tax payment) is **settled by its payment whatever the exact amount** — a bill or a
  payslip differs a little from the estimate — unless it was paid in part on purpose
  at the bank (the rest given a date: `plan_adjustments.part_paid`). A computed item can be **moved** or **cancelled** by hand
  (`plan_adjustments`); a planned row is just edited or deleted.
- **Concluded dates:** each wallet has a **concluded-up-to** date
  (`wallets.concluded_until`; Alpha's moves when a statement is concluded, any other
  wallet is concluded by hand; history in `wallet_conclusions`). An open item dated
  **after** its wallet's concluded date counts on its own date; one dated **on or
  before** it is **not matched yet** — it counts **from today** (its invoice date
  too, unless issued or part-paid) and is listed in Bank import ▸ Not matched yet.
- **What counts** for the projection, the taxes and the income tax estimate
  (`App\Support\Books::rows()`): every counted real row + the open share of every
  switched-on planned item. Nothing before the floor `managed_from` (2026-09-01 on
  live, `php artisan managed:from`) is planned; recurrences reach 24 months ahead.
- The plan is kept in the cache as plain data under one fixed key, stamped with a
  version and the day; any save (a listener in `AppServiceProvider`) or form
  submission (`FlushPlanAfterWrites`) bumps the version, so every page shows it as
  the data now stands. A rebuild takes well under a second; a cached read about 60 ms.

## Income: customers, projects, payment lines (Angel, 2026-11-19)

- **Projects** (Finance ▸ Projects, `/projects/{id}`): name, **customer** (required —
  a placeholder customer while the real one isn't known), notes and **deliverables**
  (name + net, one or more); value = the sum. Not a project tracker: no statuses.
- **Payment lines** belong to the **customer**, not a project (one plan can cover
  several projects): payment date, invoice date, **net · VAT · total** at one VAT
  rate (24 % by default, none for sales abroad; type net or total), wallet, category,
  description, **Issued** mark, on/off switch. The **invoice date follows the
  payment date** until the invoice is issued or part of the line is paid — then it
  stays put (still editable). **Paid** is derived (open amount 0); a line paid in full
  at the bank becomes **issued** too. A part-paid line can't be deleted (lower its
  amount instead); otherwise lines are deleted, not cancelled (`PaymentController`).
- **Recurring income** (rare, on the customer's page): description, net, VAT, day of
  month, from/to, wallet → creates the customer's monthly payment lines
  (`RecurringIncomeLines`); a change rebuilds only lines nobody touched (edited,
  issued or paid ones stay).
- **The customer's page** tabs: **Payments** (with "Planned X of Y net (its
  projects)") · **Projects** · **Recurring income** · Agreements · Transactions.
- **Planning ▸ Income** (`/planning/income`): every **unpaid** line by payment date,
  in **green** (money owed to us); a line from an **earlier month** carries a **!**
  (chase the customer, move the date); issued ones say "issued". **Show all** adds
  the paid ones (muted). **By customer**: customers with what they owe, each row
  opening to all its lines.

## Projection (`/projection`)

- Month by month: starting balance, income, expenses (other / payroll / contractors /
  taxes), net, ending balance — cash by payment date, from the books and the plan
  (`Books::rows()`) — with a graph (income and expense bars, balance line, current
  month marked) and the lowest balance ahead. `App\Support\Projection`.
- Controls, remembered per user (`users.preferences`): from/to month (kept relative
  to the current month, default 3 back → 12 ahead) and which wallets (transfers
  across the selection's edge then show as their own column).

## One-offs (`/planning/one-offs`)

- Expenses expected once — **planned rows** (`is_one_off`), made with the usual
  transaction form (`/one-offs`). Each counts in the plan until paid, and is done once
  real rows carrying its key cover it (matched at the bank, or "Pays planned item").
  Open ones show; **Show all** brings back the done ones, in green; a **!** marks one
  not matched in a concluded period.

## De minimis (`/de-minimis`)

- The record of every **de minimis** grant, so the table grant applications ask for
  ("Πίνακας Επιχορηγήσεων de minimis") never has to be rebuilt from paper. One row per
  approval decision or contract: programme, decision no. and date, approved amount,
  paid amount, last payment date, beneficiary + VAT no. (new rows start as Yolt Labs).
  Totals row; add/edit/delete like the other lists.
- **Download** (header button) → the whole list, oldest decision first, as **Excel**
  (real numbers and dates, `1.234,56 €`) or **Markdown**, both with the official
  table's Greek headings and a Σύνολο row (`App\Support\DeMinimisExport`).

## Bank import (`/bank-import`)

Three views (Angel, 2026-11-19), tabs in the title spot:

- **Statements:** every upload (wallet, dates, Open/Concluded, lines, who, when) and
  every wallet **concluded by hand**, newest first, plus each wallet's "complete up
  to" date with a **conclude up to…** button (for wallets without statements).
  **Upload** a statement for a wallet — **Alpha Bank CSV**
  (`App\Support\AlphaStatement`); one **open** statement per wallet at a time. Each
  line is stored once (fingerprint), so overlapping exports are safe: a line already
  in an earlier statement keeps its place there.
- **Working on a statement** — a **draft** (`App\Support\StatementDraft`): the bank
  and the app side by side for its dates (`BankComparison`). Every decision is a
  saved **step** (`bank_statement_steps`, the whole decision set as JSON) and
  **Undo** takes the last one back; **nothing in the books changes until Conclude**.
  A line's decision is **pieces adding up to it**: an existing row, a **planned item**
  (all or part — a part asks when the rest is now due), a **new row** from the add
  form (with its bank fee), a row **like** an earlier one, or the **bank fee**.
  Suggestions, in order: existing rows (as before), then **open planned items** of
  the wallet going the same way with the line's open amount, dated up to **10 days
  after** the line, any month before it (two alike → the **oldest**), then the fee,
  groups and "add like…". The upload decides the sure ones (same-day exact row or
  planned item, the bank's charges) as the draft's first step. Lines show ✓ match
  (done before), ⚠ differs, ✕ missing on a side, ● decided, ◌ part decided.
- **Conclude** needs every line decided; re-checks each decision (a row deleted,
  paired elsewhere or edited since, a planned item no longer that open → those lines
  are cleared for a second look and nothing is applied); compares the app's balance
  with the bank's closing balance (a difference must be confirmed); then applies
  everything in one database transaction — pairs rows (reconciled, bank day, bank
  amount), creates new rows and the real rows of planned items (`BankReconciler::
fromPlan`, carrying their keys), sets the rest's new date, issues fully paid payment
  lines — and moves the wallet's concluded date to the statement's end. No reopen
  (re-upload instead); **Discard** drops an open statement and its lines.
- **Not matched yet:** planned items dated in a concluded period that nothing paid.
  Each still counts from today until it is **linked** to the real transaction that
  paid it, **moved** to a new date, or **cancelled** (`PlanItemController`).
- Bank text is compared folded (`App\Support\BankText`): the bank mixes Greek and
  Latin look-alike capitals and appends changing codes.

## Wallets (`/wallets`)

- Name + starting balance, and for a bank account **where its fees go** (entity +
  expense category) — used by the form's Bank fee field and the Bank import. The **Wallets page balance counts only rows that are
  reconciled AND dated ≤ today** (owner decision 2026-10-01: unverified or future rows
  aren't real yet). The Transactions balance view counts **every** row, so each row's
  effect shows. Balances are always computed live, never stored
  (`App\Support\WalletBalances`). Each wallet's **concluded-up-to** date is kept by
  Bank import (statements, or by hand).

## Entities

- An entity has a name, optional VAT number and a **type**: customer, supplier,
  contractor, employee, state, shareholder (both-sided: dividends out / investment
  in). The type is **required** (validation and database) — including when an entity
  is created inline from the transaction form.
- Entities is one page with a tab per type (State included); each entity has a page
  (`/entities/{id}`) with an edit form, its transactions and its recurrences — a
  customer's page has the income tabs instead (see Income).

## Recurring (`/planning/recurring`)

- A **recurrence** (added on an entity's page or on Planning ▸ Recurring) is an
  **expense** template (category, wallet, description) + cadence (every N
  weeks/months/years on a day of month) + **periods**, each with its own full amount
  lines (or payroll amounts). Recurring income lives on the customer instead.
- **Payment day** (Angel, 2026-10-23): **contractors and employees** are paid on the
  **last working day of each month**; anything else on its day, or the **next working
  day** after a weekend or public holiday. A weekly cadence uses its own day. Each
  occurrence is keyed by its scheduled slot and only _dated_ on the payment day. Rule:
  `Recurrence::paysOnLastWorkingDay()` (client twin in `lib/recurring.ts`), dates via
  `App\Support\WorkingDays`.
- Occurrences are **plan items computed live** (`RecurrenceGenerator::items()`), never
  stored; a real row with the slot's key pays one.
- **Planning ▸ Recurring** (`App\Support\RecurringOverview`): every recurrence in
  one list, switched by group buttons (no counts) — **Payroll** (an employee),
  **Contractors**, **Other**. Only **active** ones show (open-ended, or still with an
  open occurrence); **Show all** brings back the **done** ones (ended, nothing open),
  in green. Payroll shows Net · FMY · EFKA employee · EFKA employer · To pay · Total
  cost; Contractors Net · VAT · Withheld · Total; Other Net · VAT · Total. Amounts are
  one occurrence of the period in force today. **+** adds one (the entity sets the
  Payroll category for an employee); a row opens its form (`?edit=<id>`).

### Payroll bonuses

An **employee's** monthly payroll recurrence (Payroll category, entity type employee —
never contractors) also plans three bonus items, described **Easter bonus**,
**Leave allowance** and **Christmas bonus**, with Net, FMY and EFKA ee/er all scaled
alike (an estimate; the payslip figure is set on reconciling). Their FMY/EFKA reach
the tax pages like a salary's. Rule: `App\Support\PayrollBonuses`; keys
`recurrence:{id}:bonus-{easter|leave|christmas}:{year}`, so a moved date updates the
same item; same floor and horizon as salaries.

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

### Payroll from the gross, leave, and leaving

- A payroll period can be **calculated**: tick "Calculate FMY and EFKA from the net
  amount" (the Net amount is the gross salary) and the three figures come from the
  tax rules in force on each pay day and the employee's children and age
  (`App\Support\PayrollCalculator`: EFKA % of gross up to the cap; FMY = scale on
  (gross − employee EFKA) × 14, minus the tax reduction, ÷ 14). Bonuses of a
  calculated period are calculated on their own amount. The form previews through
  `GET /payroll/preview` (the server's own calculator — no client copy). Periods that
  existed before keep their typed figures until ticked.
- An **employee's page** has: **Employment** (hired/left, born, dependent children,
  working week, years worked elsewhere, optional leave-days override); **Leave**
  (entries from–to, days counted from the dates without weekends/holidays unless
  typed; per year: days by law — 20 pro rata in the hiring year, 21, then 22; 25 with
  10 years here or 12 in all; 26 with 25; 24/25/26/30/31 on a 6-day week — taken and
  left; `App\Support\Leave`); and **If they leave** (`App\Support\Termination`): for
  a chosen date, severance (by completed years: <1 none, 1–<4 2 months, 4–<6 3, 6–<8
  4, 8–<10 5, 10→6 … 15→11, 16+ 12; a month = pay up to 8×30×minimum daily wage, +1/6;
  halved with notice), unused leave (days × monthly ÷ 25), and the leave allowance and
  running bonus earned so far if not yet paid. Gross, an estimate for planning.

## Taxes (`/planning/taxes`)

- Each tax is a **bucket** per (invoice month, tax) that emits payment **obligations**
  `{amount, due date}`, computed from the books **and the plan** (`Books::rows(false)`).
  All taxes attribute by **`invoice_date`**. Monthly taxes are due on the **last
  working day of the following month** (weekends + public holidays excluded).
- **VAT:** output − input per month; a credit rolls forward; a debit is paid in one go.
- **Withholding, FMY, EFKA:** amounts collected in a month, paid the next month.
- **Income tax:** **estimated** per fiscal year from the books and the plan until the
  accountant's figure is entered (`App\Support\IncomeTaxEstimate`): profit = income
  net − expense net − employer EFKA (by invoice date; tax payments, transfers,
  shareholder rows and categories with "counts toward profit" off are left out); tax =
  22 % of a positive profit + business levy; plus the advance toward next year (80 %),
  less the advance already paid; in 8 monthly installments from July of the next
  year. Entering a year at `/planning/taxes/income` replaces the estimate.
- Planning ▸ Taxes opens on a **month-by-month payment schedule**, then a card per tax
  and the **tax wallet** selector; each tax page shows its ledger and the transactions
  feeding it.
- **Tax payments are planned items** (State, category Taxes, on the due date, in the
  tax wallet) — matched at the bank like any other.

## Agreements (`/agreements`, and on every entity's page)

- For the record: title, the entity, start, end (empty = open-ended), notes and the
  signed **file** (PDF/Office/image up to 20 MB), stored privately
  (`storage/app/private/agreements`) and opened only by signed-in users. Status:
  Not started · Active · **Ends in N days** (within the warning window — 30 days,
  changeable on the page; also listed on Home) · Ended (hidden unless Show all).
  `backup-db.sh` archives the files with each database backup.

## Configuration (`/configuration`)

- **Categories:** side-by-side Income / Expense lists with one search + one Add; Add
  can create **Both** (one of each). Each category has a page with an edit form and
  its transactions, with bulk **Move** to another category / **Delete**. A category
  can't be deleted while live transactions use it; once unused it's removed for good.
- **VAT rates**, **Withheld tax rates** (name + %). A rate's row opens its page of
  **dated changes** ("from this date the rate is X %"); amounts use the rate in force
  on the **invoice date** (`App\Support\Rates`, client twin `ratePct`).
- **Tax rules**: dated sets of the law's figures — EFKA shares and cap, the payroll
  tax scale (brackets × children, young-employee rates, tax reduction), company tax
  (rate, advance, levy, installments), the minimum daily wage for the severance cap.
  A change in the law is a new set from its date (it starts as a copy), so earlier
  dates keep the old law (`App\Support\TaxRules`, defaults + sources in
  `TaxRuleDefaults`).
- **Whenever a rule changes** (a rate, a dated change, the tax rules, an employee's
  details) `App\Support\Recalculate` re-derives everything **not reconciled** and
  rebuilds the tax payments; reconciled rows never move.
- A category has **Counts toward the company's profit** (off for pass-through money
  like deposits or tax payments) — used by the income tax estimate.
- **Public holidays** (date + name).
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
