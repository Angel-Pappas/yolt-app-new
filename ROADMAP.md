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
- [x] Entities by type (incl. shareholders and the Cheese bucket), entity pages.
- [x] Recurring transactions with periods, generated over a rolling 24-month horizon.
- [x] Taxes: VAT, withholding, FMY, EFKA, income tax; monthly payment schedule; tax
      payments generated as transactions automatically; public holidays.
- [x] Configuration: categories (with per-category page and bulk move), VAT and
      withheld rates, public holidays, users (admin, invite link).
- [x] Shared list view: sticky header, per-column filters, tri-state sort,
      load-as-you-scroll, totals row.
- [x] Way of working: design principles, fast screenshots on demo data, one-command
      ship, frontend unit tests, deploy safety check.

## Next (suggested)

- [ ] **Users page onto the shared list view** — it's the last hand-rolled table
      (`pages/users/index.tsx`), so it lacks search, column filters and sorting.
- [ ] **Amounts with thousands separators** — typing `1.234,56` into an amount field is
      read as 0 (only `1234,56` works); accept the Greek thousands dot too, client and
      server. (Found by the new frontend unit tests, 2026-10-15.)
- [ ] **Projections / cash-flow view** — the future-balance view the recurring and tax
      transactions were built for (deferred since 2026-09-21).
- [ ] **Home page content** — currently just the logo and a welcome.

## Ideas / later

- [ ] Server-side enforcement of entity type per transaction side, once Cheese is empty.
- [ ] Notifications (e.g. tax payments due soon, unreconciled rows piling up).
