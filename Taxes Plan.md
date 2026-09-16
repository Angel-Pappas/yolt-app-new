# Taxes Rebuild — Build Plan

Working plan for rebuilding the Taxes area on the "buckets & obligations" model.
**Update the checkboxes and the progress log as each step completes.** Keep each
slice small, green, and pushed to `main` (Direction 2). Run the full local verify
from `AGENTS.md` before every push. Write Pest tests for every calculation (Direction 7).

---

## 1. The model (what we're building toward)

- A **bucket** = `(month, tax)`. It accumulates tax amounts from transactions and
  emits **payment obligations** `{ amount, due_date }`.
- Obligations are the single primitive both views render, and the exact spec the
  future auto-transaction feature will turn into real transactions.

### Universal rules (do not violate)

- **Attribution date = `invoice_date`, always**, for _every_ tax (VAT and withheld
  alike). Never payment `date`. (Withheld currently uses `date` — must be switched.)
- **Due date** for a monthly tax's month-M bucket = the **last working day (Mon–Fri)
  of month M+1**. Working days exclude weekends **and** stored public holidays.
  Example: September bucket → paid the last working day of October.
- Derived values are **never stored** — buckets/obligations are computed live
  (except income tax, which is a manual per-year record — see slice 4).
- Tax-payment transactions (money to the state) are just expenses with a **0% VAT
  rate** — no special flag/type. 0 VAT ⇒ they feed no bucket.

### Per-tax calculation

| Tax            | Bucket amount (by `invoice_date` month)             | Rollover                         | Obligation                                                                                          |
| -------------- | --------------------------------------------------- | -------------------------------- | --------------------------------------------------------------------------------------------------- |
| **VAT**        | Σ income VAT − Σ expense VAT                        | **Yes** (credit carries forward) | full positive amount, 1 payment, due M+1. **No installment split.**                                 |
| **Withheld**   | Σ withheld                                          | No                               | full amount, due M+1                                                                                |
| **FMY**        | Σ `fmy_amount`                                      | No                               | full amount, due M+1                                                                                |
| **EFKA**       | Σ (`efka_employee_amount` + `efka_employer_amount`) | No                               | combined, one payment, due M+1                                                                      |
| **Income tax** | manual per-year record                              | n/a                              | one installment per month in [first…last], each = monthly amount, due that month's last working day |

### Payroll (FMY/EFKA capture) — manual until real payroll is built

- Triggered by the category **named exactly "Payroll"** (name-match, **no** `is_payroll` flag).
- When Payroll is the selected category, the transaction form **morphs**: hide VAT
  lines, withheld, and the Net/Total toggle; show 4 manual inputs — **Net, EFKA
  Employee, EFKA Employer, FMY**.
- Summary/display row order: **Net · FMY** (red, −) **· EFKA ee** (red, −) **· To Pay**
  (=Net−FMY−EFKA ee) **· EFKA er** (normal) **· Total Cost** (=Net+EFKA er).
- New nullable `decimal(12,2)` columns on `transactions`: `fmy_amount`,
  `efka_employee_amount`, `efka_employer_amount`. Plain amounts, no rates/lines.
- **Cash/wallet formula extends to `net + vat − withheld − fmy_amount −
efka_employee_amount`** (all default 0). Non-payroll rows unchanged; payroll rows
  move the wallet by **To Pay**. **EFKA Employer never touches the wallet** — pure
  liability, leaves later via the EFKA payment. Transactions table needs no payroll
  special-casing: Net col = `net`, VAT col = 0, Total col = To Pay — automatic.
- Reconciliation check: To Pay + FMY + EFKA(ee+er) = Net + EFKA er = Total Cost. ✅
- VAT column in the transactions table **stays for now**; remove only when the user says.

### Views

- **`/taxes`** = per-month **payment schedule** ("what I pay this month", default =
  current month, navigable): each tax's amount + shared due date + total.
- **`/taxes/{tax}`** = per-tax bucket ledger + due dates + a **contributing-transactions
  `DataTable`** (reuse the `categories/show.tsx` pattern). Income tax's page shows the
  yearly records + generated installments instead.

---

## 2. Codebase anchors (verified 2026-09-16)

- Ledgers: `app/Support/VatLedger.php`, `app/Support/WithheldLedger.php`.
- Controller: `app/Http/Controllers/TaxController.php`; pages `resources/js/pages/taxes/`.
- Config lists: routes in `routes/web.php` under the `configuration` prefix; nav in
  `resources/js/config-nav.ts` (`configItems` + `isConfigPath`); tiles page
  `resources/js/pages/configuration/index.tsx`.
- Lookup CRUD pattern to mirror: `WithheldTaxRateController`, its migration/model/test,
  and `resources/js/pages/withheld-tax-rates/index.tsx` using
  `resources/js/components/crud/crud-resource.tsx`.
- `CrudResource` field types today: `text | decimal | select | textarea` — **no date
  field** (slice 1 adds one, using `resources/js/components/ui/date-field.tsx`).
- Transactions: `app/Http/Controllers/TransactionController.php`,
  `resources/js/pages/transactions/transaction-form-dialog.tsx`; wallet math in
  `app/Support/WalletBalances.php`; money/format helpers `resources/js/lib/format.ts`.

---

## 3. Slices (build in order; each is independently shippable)

### Slice 1 — Public holidays + `lastWorkingDay` helper ✅ DONE (2026-09-16)

- [x] Migration `public_holidays` (`id`, `user_id` nullable audit FK, `date`, `name`
      nullable, timestamps, softDeletes, index on `date`).
- [x] `PublicHoliday` model (`#[Fillable]`, `SoftDeletes`, `date:Y-m-d` cast) + factory.
- [x] `PublicHolidayController` (index/store/update/destroy) mirroring
      `WithheldTaxRateController`; validates `date` (required, date, unique among active
      via `Rule::unique(...)->whereNull('deleted_at')`) + `name` (nullable). Toast flashes.
- [x] Routes under `configuration` prefix; added to `config-nav.ts` (`CalendarDays` icon).
- [x] Added a **`'date'` field type** to `CrudResource` (renders `DateField`); page
      `resources/js/pages/public-holidays/index.tsx` uses it, column shows `formatDate`.
- [x] `App\Support\WorkingDays::lastWorkingDay(int $year, int $month, ?array $holidays)`
      — walks back skipping Sat/Sun + holidays. `holidaySet()` loads the set once
      (`array_fill_keys` for the precise `array<string,true>` type PHPStan wants).
- [x] Tests: `WorkingDaysTest` (weekday/weekend/holiday/stacked rollback + DB-backed),
      `PublicHolidayTest` (view/create/validate/unique/soft-delete/guest).
- [x] Verify all (178 tests green, phpstan/pint/tsc/build clean); Summary.md updated.

### Slice 2 — Bucket → obligation abstraction + refactor VAT/Withheld ✅ DONE (2026-09-16)

- [x] `App\Support\TaxObligation` value object (`tax`, `period`, `amount`, `dueDate`,
      `toArray()`). Each ledger exposes `monthly()` (table rows) + `obligations()`.
- [x] `VatLedger` refactored: **installment split dropped** (1 payment of the full
      positive amount), **credit rollover kept**, each row gains `payable` + `due_date`
      (= `WorkingDays::lastWorkingDay` of the following month).
- [x] `WithheldLedger` refactored: keyed off **`invoice_date`**, each row gains
      `due_date`; only active months emitted (nothing carries between them); doc fixed.
- [x] `TaxController@index` now computes each card's "payable this month" from the
      obligations **due in** the current month (correct under the M+1 model).
- [x] Pages updated: `taxes/vat` (Payable + Due date columns, no installment column),
      `taxes/withheld` (Due date column, invoice-date month link).
- [x] Tests: `VatLedgerTest` (single month, credit rollover, no-split, obligations,
      credit-only → none, holiday shift), `WithheldLedgerTest` (invoice-date attribution,
      obligations, summing, income ignored). 178 tests green; full verify clean.

### Slice 3 — FMY + EFKA (payroll)

- [ ] Migration: add `fmy_amount`, `efka_employee_amount`, `efka_employer_amount`
      (nullable `decimal(12,2)`) to `transactions`. Model fillable/casts.
- [ ] Extend cash formula everywhere it's derived (`WalletBalances`, the transactions
      list "Total", any `computeTotal` equivalent): `+ net + vat − withheld − fmy −
efka_employee`. Confirm non-payroll rows are unchanged (new fields default 0/null→0).
- [ ] Transaction form morph: when selected category name === "Payroll", swap the
      amounts area for the 4 inputs and render the payroll summary row (Net · FMY− ·
      EFKA ee− · To Pay · EFKA er · Total Cost). Hide VAT/withheld/Net-Total toggle.
- [ ] `TransactionController` validation: accept the 3 payroll fields; when payroll,
      ignore/zero VAT+withheld lines. Persist the 3 amounts; server-derive nothing that
      the client shouldn't be trusted for (amounts are manual, but re-validate numerics).
- [ ] `FmyLedger` + `EfkaLedger` (monthly, by `invoice_date`, no rollover, due M+1;
      EFKA sums ee+er). Add both to `TaxController@index` cards + their `/taxes/{tax}` pages.
- [ ] Tests: cash formula (payroll row → wallet moves by To Pay, EFKA er excluded;
      normal row unchanged); form persistence; FMY/EFKA ledger sums + due dates.
- [ ] Verify; commit; push; CI green. Update Summary.md + progress log.

### Slice 4 — Income tax (stored per-year)

- [ ] Migration `income_tax_years` (`id`, `user_id`, `year` unique, `revenue_before_tax`,
      `total_tax`, `first_installment_month` + `last_installment_month` (store as `YYYY-MM`
      or a date on the 1st), `monthly_installment_amount`, timestamps, softDeletes).
- [ ] Model + controller (CRUD) + routes. Page under `/taxes/income` (list of years +
      add/edit; show generated installment schedule per year).
- [ ] `IncomeTaxLedger`: one obligation per month in [first…last] = monthly amount, due
      that month's last working day.
- [ ] Tests: schedule generation (range inclusive, monthly amount, holiday-affected due
      dates); CRUD.
- [ ] Verify; commit; push; CI green. Update Summary.md + progress log.

### Slice 5 — The two views

- [ ] `/taxes` per-month payment schedule (default current month, prev/next nav): gather
      all obligations whose `due_date` falls in the viewed month across every tax; show
      tax · period · amount · due date + a total.
- [ ] `/taxes/{tax}` per-tax pages: bucket ledger + due dates + contributing-transactions
      `DataTable` (mirror `categories/show.tsx`). Income tax page = yearly records + schedule.
- [ ] Tests: per-month aggregation picks the right obligations; per-tax transaction query.
- [ ] Verify; commit; push; CI green. Update Summary.md + progress log.

### Later (not now)

- Income-tax autofill from computed results; auto-generation of future tax-payment
  transactions from obligations (the big forward feature this model is built for);
  removing the VAT column from the transactions table (on user's say-so).

---

## 4. Per-slice verify checklist (from AGENTS.md)

```
pnpm run check:fix
pnpm run types:check
php vendor/bin/pint --parallel
php vendor/bin/phpstan analyse
pnpm run build
php artisan test
```

---

## 5. Progress log

- 2026-09-16 — Plan created. Design agreed with user across the preceding discussion
  (buckets/obligations, invoice-date universal, last-working-day M+1, payroll form &
  cash formula, income tax manual form, public-holidays config).
- 2026-09-16 — **Slice 1 done.** Public holidays config list (`/configuration/public-holidays`)
    - `App\Support\WorkingDays` (`lastWorkingDay`/`holidaySet`). Added a `date` field type
      to the shared `CrudResource`. Full verify clean.
- 2026-09-16 — **Slice 2 done.** `App\Support\TaxObligation`; VAT/Withheld ledgers
  refactored onto buckets+obligations with real due dates (last working day of M+1); VAT
  installment split removed (rollover kept); withheld switched to `invoice_date`. Pages +
  index cards updated. Ledger tests added; full verify clean. Next: Slice 3 (FMY/EFKA).
