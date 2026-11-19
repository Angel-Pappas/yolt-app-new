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
      and fees settled on upload, drag to pair; rows follow the bank's order within a
      day; Bank fee on the add form (2026-11-16, side by side 2026-11-17).
- [x] Way of working: design principles, fast screenshots on demo data, one-command
      ship, frontend unit tests, deploy safety check.

## Next (suggested)

- [ ] **The plan beside the books** (agreed with Angel 2026-11-19; build next). Angel
      does the books about once a month; the 500+ generated future rows clog
      Transactions. Transactions becomes **only what really happened**; the future is a
      live-calculated plan that feeds Projection and Taxes and is matched at the bank
      upload.
    - **Transactions = history.** Nothing generates future rows any more. Future
      one-offs and project income instalments become planned items too, so no row in
      Transactions has a future date unless typed by hand.
    - **The plan** = recurrences (salaries + bonuses), the taxes they cause, one-offs,
      expected income. Calculated live, never stored. Each planned item has a fixed
      identity (e.g. "recurrence 7, Oct 2026"); its **open amount = planned − real
      transactions linked to it**. No "close the rest" button (Angel: a leftover is
      settled with a correcting transaction).
    - **Concluded date per wallet.** Alpha's moves only by concluding a statement;
      every other wallet (no statements) gets a hand-set "Concluded up to" date. Up to
      it only real transactions count (= the bank); after it real transactions + open
      planned items. Statements never have gaps (Angel overlaps them on purpose).
    - **Statement uploads page:** a list of uploads (dates covered, who/when, Open or
      Concluded) plus the hand conclusions of other wallets, so "where do I start" is
      at a glance. An open upload is a **draft**: saved on every click, **Undo** step by
      step, nothing in Transactions changes while working. Only one open upload per
      wallet (guarded).
    - **Matching order:** lines already handled in an earlier upload show as done
      (overlap; identical same-day lines told apart by their order) → existing
      transactions → open planned items dated up to **10 days after** the statement's
      end, previous months included (two exact matches → suggest the oldest) → the
      current "add like…" suggestions. **Partial match:** linking 1k to a 2k item leaves
      1k open and asks for its new date.
    - **Conclude** needs every line handled, re-checks anything changed meanwhile
      (shown for a second look, never applied blindly), then applies everything at
      once and moves the concluded date to the statement's end. No reopen/undo after
      (Angel: re-upload the statement if needed).
    - **Not yet matched:** planned items dated on/before their wallet's concluded date
      that are still open — a list (note on Home) to link, move or cancel each.
      Projection counts them from today, not on their old date.
    - Editing a transaction before a concluded date is allowed, with a note that it
      changes a concluded period.
    - **Projection and Taxes** run on real rows up to each concluded date + the open
      plan after it.
    - **Cutover:** imports already done on live are marked concluded up to their last
      date; the generated rows nobody confirmed (525 on 2026-11-19) are deleted —
      **Angel's yes given 2026-11-19**; confirmed ones stay as history; DB backed up
      first.
    - **Build order** (each shipped on its own, checked against today's figures): (1)
      the plan calculation, Projection + Taxes moved onto it with identical numbers;
      (2) concluded dates + the not-yet-matched list; (3) the draft upload page; (4)
      clean-up of the old generated rows.
- [ ] **Later — ask Angel how to make Bank import smarter** (his note, 2026-11-19):
      raise it once the plan above is live; don't start unasked.
- [ ] **Users page onto the shared list view** — it's the last hand-rolled table
      (`pages/users/index.tsx`), so it lacks search, column filters and sorting.
- [ ] **Tick payroll periods to "calculated"** where wanted — existing periods kept
      their typed figures.

## Ideas / later

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
