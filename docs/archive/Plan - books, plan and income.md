# Plan — the books, the plan and income (2026-11-19)

The combined build plan for the three designs agreed with Angel on 2026-11-19 (the new
Finance/Accounting layout, the plan beside the books, income as customers/projects/
payments), with the gap-fillers found while planning. Angel's answers before the build:
ship however is cheapest (nobody uses the app meanwhile); part-paid lines keep their
invoice date (Claude's call — Angel will say if it doesn't fit); the include/exclude
switch lives on payment lines, one-offs and recurrences (projects lose it); future
dates in the transaction form are allowed with a warning. Moved to `docs/archive/` when
done.

## 1. Core idea

- **Real** = the `transactions` rows that happened (what Transactions shows).
- **Plan** = everything still expected. Three sources, one shape (a `Transaction`
  object with a **plan key**):
    - **computed, never stored:** recurrence occurrences (salaries, bonuses, supplier
      and contractor costs) `recurrence:{id}:{slot}` / `recurrence:{id}:bonus-…`, and
      tax payments `tax:{tax}:{period}` — exactly what the generators made before, now
      built in memory (`App\Support\Plan`);
    - **stored, hand-made:** planned one-offs and customer payment lines — rows of the
      same `transactions` table flagged **`is_planned`**, hidden from every real query
      by a global scope; their own model `PlannedTransaction` sees only them. Key
      `planned:{id}`. Reusing the table reuses the whole amount machinery (VAT lines,
      withholding, payroll fields, the form).
- **Fulfilment:** a real transaction carrying a plan key (`managed_key`) pays that
  item. **Open amount = the item's cash − the cash of the real rows carrying its key.**
  Existing reconciled generated rows already carry their keys, so history fulfils
  itself. Open ≤ 0 → done.
- **Adjustments** for computed items (a moved date, a cancel) live in
  `plan_adjustments` (key, date, cancelled); stored items are just edited/deleted.
- **Floor:** nothing is planned before `managed_from` (2026-09-01); horizon 24 months.

## 2. Concluded dates

- `wallets.concluded_until` (date, null = nothing concluded). Alpha's moves when a
  statement is concluded; any wallet can be concluded up to a date by hand (Bank
  import ▸ Other wallets). Every conclusion is recorded in `wallet_conclusions`
  (wallet, until, who, when, statement or by hand) — the history list.
- **What counts** (Projection, taxes, income tax estimate, Home):
  every real (counted) row + every open, switched-on plan item scaled to its open
  share. An item dated **after** its wallet's concluded date counts on its own date;
  one dated **on/before** it is **Not matched yet** and counts **from today** (date,
  and invoice date unless issued/pinned, become today).
- **Not matched yet** = open items on/before their wallet's concluded date — a list in
  Bank import (Link to a transaction · Move date · Cancel/Delete); the Bank import
  sidebar item shows **!** while it isn't empty.
- Editing a real transaction dated on/before its wallet's concluded date: the form
  shows "This changes a concluded period" (allowed).

## 3. Statement uploads (Bank import)

- `bank_statements`: wallet, uploaded by/at, from/to dates (the file's lines), closing
  balance, **status open/concluded**, concluded by/at. Lines (`bank_lines`) belong to
  the statement that first brought them; a line seen before (overlap) is recognised by
  its fingerprint and shows as already handled.
- **Draft:** an open statement keeps its decisions in `bank_statement_steps` (one row
  per action, the whole decision set as JSON) — every click saved, **Undo** pops the
  last step. Nothing in Transactions changes while working.
- **A decision per line** = pieces adding up to the line: existing transactions
  (whole), plan items (all or part of the open amount; a part asks the rest's new
  date), a new transaction (form data, incl. "like this row" and the bank fee).
- **Suggestions**, in order: existing transactions (as today) → open plan items of the
  wallet dated up to 10 days after the statement's end, any earlier month (equal ones →
  the oldest) → bank fee → groups → "add like…".
- **Conclude** (needs every line decided): re-checks every referenced row/item (gone,
  paired elsewhere, edited after the decision → the line is flagged for a second look,
  nothing applied); compares the app balance with the bank's closing balance (a
  difference is shown and must be confirmed); then in one database transaction pairs
  rows, creates the new rows (from plan items: copied with their key, scaled to the
  piece), stores remainder dates, marks fully paid payment lines issued, and moves the
  wallet's concluded date. One open statement per wallet.
- **List page:** uploads (dates, who/when, Open/Concluded) + hand conclusions of other
  wallets, newest first; a statement opens its draft (open) or its read-only result.

## 4. Income

- **Projects** (Finance ▸ Projects): name, **customer (required; a dummy customer
  when unknown)**, notes, **deliverables** (`project_deliverables`: name + net, one or
  more). Value = Σ deliverables. No money switch, no instalments.
- **Payment lines** (`PlannedTransaction`, type income, customer): payment date,
  invoice date, net · VAT · total (one VAT rate, default 24%, 0% abroad; type net or
  total), wallet (default the statement wallet), description, **Issued** mark, switch.
  Invoice date follows the payment date until **Issued** or **part-paid** (then it stays;
  editable by hand). Paid = open amount 0 (derived); fully paid at conclude ⇒ Issued.
- **Recurring income** (`recurring_incomes`, rare, per customer): description, net,
  VAT rate, day of month, from/to, wallet → creates payment lines (`recurring_income_id`
    - slot); a change rebuilds only untouched lines (not paid, not part-paid, not issued,
      not hand-edited).
- **Customer page** tabs: Details · Projects · Payments · Recurring income ·
  Transactions. Payments shows "Planned X of Y net" (Σ lines net vs Σ project value).
- **Planning ▸ Income:** unpaid lines by payment date, **green**; past months carry
  **!**; Issued shown. **Show all** adds paid ones (muted grey). **By customer** view:
  customers listed, each expands to all its lines.
- Old income tags on transactions (`income_status`, `project_id`) stop being used
  (columns kept; no data dropped).

## 5. One-offs

- A planned one-off = `PlannedTransaction` (expense, `is_one_off`) made with the usual
  transaction form. Planning ▸ One-offs lists open ones; **Show all** brings back the
  done ones (green, as Recurring). The 4 one-offs on live become planned (they are all
  unreconciled).

## 6. Layout

- Sidebar: Home · **Finance**: Planning, Projection, Projects · **Accounting**:
  Transactions, Bank import (!), Wallets, Entities, Agreements, De minimis,
  Configuration.
- **Planning** tabs: Income · Recurring · One-offs · Taxes (`/planning/{tab}`;
  `/income`, `/recurring`, `/one-offs`, `/taxes…` redirect). Recurring = expense
  recurrences only, second row Payroll · Contractors · Other. Taxes = the tax schedule
  and per-tax pages (`/planning/taxes/vat` …).
- **Entities** tabs: Customers · Suppliers · Contractors · Employees · Shareholders ·
  State (`/entities/{type}`).
- One shared `PageTabs` (names only, no counts anywhere); the sidebar link and a bare
  `/planning` / `/entities` open the tab last used. Back links on detail pages return
  to the tab they came from.

## 7. Everything else that must follow

- Recurrence form: expense only (customers are not offered; recurring income lives on
  the customer). Recurring's "done" = ended with no open item left.
- Transaction form: **"Pays planned item"** picker (open items of the chosen entity,
  ±60 days, best guess preselected) so hand-typed rows fulfil the plan; future-date
  warning; concluded-period warning.
- Projection: real + plan as in §2; the "count pending" switch goes (overdue items
  now count from today by rule).
- Taxes pages and the income tax estimate run on real + plan.
- Home: Not matched yet, next tax payments and money owed come from the plan.
- Daily `managed:sync` and the sync calls after writes go (nothing to sync);
  `Recalculate` keeps re-deriving unreconciled real rows' VAT only.
- Wallets page, Transactions balance view, entity/category transaction lists: real
  rows only (unchanged, via the global scope).
- Termination / leave / payroll preview keep using the recurrence amount helpers.
- UI audit list and screenshots cover the new pages.

## 8. Cutover (one release, DB backed up by deploy)

1. Migrations: `is_planned`, `invoice_pinned`, `is_issued`, `recurring_income_id`,
   `recurring_slot`, `is_edited` on transactions; `plan_adjustments`,
   `wallets.concluded_until`, `wallet_conclusions`, `bank_statements`,
   `bank_statement_steps`, `bank_lines.statement_id`, `project_deliverables`,
   `recurring_incomes`.
2. Data step: delete the generated rows nobody confirmed (unreconciled, `source`
   recurrence/tax — Angel's yes 2026-11-19); the 4 unreconciled one-offs become
   planned; existing bank lines go into one statement — concluded up to the day before
   the first unhandled line (Alpha's concluded date), the unhandled lines in a new open
   statement; projects' agreed totals become one deliverable each.
3. Before/after check: Projection's monthly figures captured on live before the deploy
   and compared after.
