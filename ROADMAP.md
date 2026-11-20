# Roadmap

What's built, what's next, and ideas. Tick items off and add new ones with every
change (details of how things work: `Summary.md`; what changed and why: `History.md`).
Angel sets the priorities — the order under **Next** is a suggestion until he does.

## Built

- [x] Transactions: income/expense/transfer, multi-VAT lines, Net/Total, per-line
      withholding, payroll (FMY/EFKA), reconcile, invoice month, quick filters, date
      presets, Add + New/Same, in-place entity/category creation.
- [x] Balance view per wallet with running balance and a Starting balance row.
- [x] Wallets with live balances (reconciled + dated ≤ today).
- [x] Entities by type (incl. shareholders), entity pages; every entity has a type
      (the Cheese bucket was emptied and removed 2026-10-23).
- [x] Recurring transactions with periods, generated over a rolling 24-month horizon.
- [x] Finance ▸ Recurring: every recurrence in one list, grouped Payroll / Contractors
      / Expenses / Income, with entity, amounts and date range; add, edit and delete
      recurrences right there (2026-10-23).
- [x] Finance ▸ De minimis: the record of every de minimis grant (started with the
      5 grants up to 2026-10-30), downloadable as Excel or Markdown (2026-10-30).
- [x] Payroll bonuses: each employee's payroll recurrence also generates the Easter
      bonus, leave allowance and Christmas bonus, pro rata to the days employed and
      the salary periods (2026-10-23).
- [x] Finance ▸ One-offs (tagged one-off expenses) and "Show all" on One-offs and
      Recurring: open items by default, done ones in green (2026-10-23).
- [x] Recurring payment days avoid weekends/holidays: contractors and employees on
      the last working day of the month, the rest on the next working day (2026-10-23).
- [x] Taxes: VAT, withholding, FMY, EFKA, income tax; monthly payment schedule; tax
      payments generated as transactions automatically; public holidays.
- [x] Configuration: categories (with per-category page and bulk move), VAT and
      withheld rates, public holidays, users (admin, invite link).
- [x] Shared list view: sticky header, per-column filters, tri-state sort,
      load-as-you-scroll, totals row.
- [x] Money inputs show thousands dots (`1.234,56`) when not being edited (2026-10-15).
- [x] On/off switch on transactions, recurrences and projects (what-if), income
      status tags, Projects with installments, Finance ▸ Income (2026-11-12).
- [x] Date-sensitive VAT/withholding rates and dated Tax rules; payroll calculated
      from the gross; Employment details, leave tracking and termination cost
      (2026-11-12).
- [x] Income tax estimated from running profit until the accountant's figure is in
      (2026-11-12).
- [x] Finance ▸ Projection with graph; Home dashboard (2026-11-12).
- [x] Agreements with files and renewal warning (2026-11-12).
- [x] Lists remember their setup; every row that opens something opens in a new tab
      (2026-11-12).
- [x] Finance ▸ Bank import (Alpha Bank CSV): the bank and the app side by side, each
      row a pair with a verdict (match / differs / missing on one side), sure pairs
      and fees settled on upload, drag to pair (the list scrolls while dragging,
      2026-11-20); rows follow the bank's order within a
      day; Bank fee on the add form (2026-11-16, side by side 2026-11-17).
- [x] Books, plan and income (2026-11-19): Transactions is history; the plan
      (recurrences and taxes live, one-offs and customer payment lines as planned
      rows) feeds Projection and Taxes; concluded dates per wallet; Bank import as an
      undoable draft with Conclude, statement history and Not matched yet; projects
      with deliverables; payment lines and recurring income on the customer; the
      Finance / Accounting sidebar with tabbed Planning and Entities.
- [x] Way of working: design principles, fast screenshots on demo data, one-command
      ship, frontend unit tests, deploy safety check.

## Next (suggested)

- [ ] **Ask Angel how to make Bank import smarter** (his note, 2026-11-19): the plan
      is live now — raise it with him; don't start unasked.
- [ ] **Users page onto the shared list view** — it's the last hand-rolled table
      (`pages/users/index.tsx`), so it lacks search, column filters and sorting.
- [ ] **Tick payroll periods to "calculated"** where wanted — existing periods kept
      their typed figures.
- [ ] **Dependency day** (health check 2026-11-20): the minor updates in one ship
      (Laravel 13.43, Inertia, Fortify, Pest, React 19.3…), then each major on its own
      (TanStack Table 9 and TypeScript 7 most likely need code changes; lucide 1,
      vite-plus 1).
- [ ] **Move to the next Node LTS before 30/04/2027**, when Node 22 support ends
      (dev, CI and live together).
- [ ] **Page `breadcrumbs` are set but never shown** (health check 2026-11-20): many
      pages pass a `breadcrumbs` list to the layout, which no longer draws them —
      remove the prop and the type.

## Ideas / later

- [ ] **A proper mobile pass** over every page (Angel, 2026-11-19: not checked yet;
      "somewhat decent" until then).

- [ ] Income tax: carry losses forward; the reduced advance for a company's first
      years.
- [ ] Severance: the extra for staff with 17+ years on 12/11/2012 (not needed today).
- [ ] Leave: month-by-month accrual in the first two years (the whole year's figure is
      shown now).
- [ ] Transactions: remember the last date range when returning from elsewhere.

- [ ] De minimis: show the rolling 3-year total against the €300.000 ceiling
      (Regulation (EU) 2023/2831), so the declaration's last check is at a glance.
- [ ] Server-side enforcement of entity type per transaction side (Cheese is gone, so
      this is now possible).
- [ ] Notifications (e.g. tax payments due soon, unreconciled rows piling up).
