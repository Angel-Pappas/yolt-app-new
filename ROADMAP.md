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
- [x] Way of working: design principles, fast screenshots on demo data, one-command
      ship, frontend unit tests, deploy safety check.

## Next (suggested)

- [ ] **Payroll bonuses** — Christmas, Easter and leave allowance generated from each
      employee's payroll recurrence. Not built; rules, design and open questions in
      `docs/payroll-bonuses.md` (2026-10-23).
- [ ] **Users page onto the shared list view** — it's the last hand-rolled table
      (`pages/users/index.tsx`), so it lacks search, column filters and sorting.
- [ ] **Projections / cash-flow view** — the future-balance view the recurring and tax
      transactions were built for (deferred since 2026-09-21).
- [ ] **Home page content** — currently just the logo and a welcome.

## Ideas / later

- [ ] Server-side enforcement of entity type per transaction side (Cheese is gone, so
      this is now possible).
- [ ] Notifications (e.g. tax payments due soon, unreconciled rows piling up).
