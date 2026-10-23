# Payroll bonuses — Christmas, Easter, leave allowance (planned, not built)

Hand-off note for a fresh session (written 2026-10-23). Angel asked whether payroll
covers the three statutory payments. **It doesn't yet.** This file is the starting
point; read `Summary.md` ("Recurring & managed transactions") and `AGENTS.md`
("Recurrences") first.

## Where things stand

- Payroll today = an employee's **recurrence** in the **Payroll** category: one
  monthly payment per period with manual Net (gross) · FMY · EFKA employee · EFKA
  employer (`recurrence_entries.net / fmy_amount / efka_*`). To pay = Net − FMY −
  EFKA ee; cost = Net + EFKA er.
- `App\Support\RecurrenceGenerator` turns it into managed transactions over a rolling
  24-month horizon. Each row is keyed by its **slot** (`managed_key =
recurrence:{id}:{Y-m-d}`) and dated on its **payment day** (`paymentDate()`:
  contractors/employees → last working day of the month via `WorkingDays`).
  Reconciled rows are frozen.
- `TaxSync` turns FMY/EFKA on transactions into tax payments, so anything generated
  as a payroll transaction feeds the tax pages automatically.
- **No bonus exists anywhere** (no code, no data). Future cash and cost are
  understated by roughly two salaries per employee per year.

## The rules

Confirmed from current sources (2026-10-23):

- **Easter bonus (δώρο Πάσχα):** accrues 1 Jan – 30 Apr; full period = **½ monthly
  salary** (or 15 daily wages); partial periods pro rata. Based on the regular salary
  paid **15 days before Easter**. Cash only. Subject to EFKA and salary income tax.
  Due by **Holy Wednesday** (Orthodox Easter; 2026 Easter = 12 April). The April 2026
  minimum-wage rise was not counted for the 2026 bonus.

**To verify before building** (from memory — check official sources, record them here):

- **Christmas bonus (δώρο Χριστουγέννων):** accrues 1 May – 31 Dec; full period = **1
  monthly salary** (or 25 daily wages); pro rata otherwise; due by **21 December**.
- **Leave allowance (επίδομα αδείας):** up to **½ monthly salary** (13 daily wages
  for 6-day staff), paid when the annual leave is taken (in practice often summer).
- The **×1,04166** factor applied to the Christmas and Easter bonuses (it folds in
  the leave-allowance share). Confirm what it is and when it applies.
- Whether EFKA rates on bonuses equal the monthly rates (likely) and how FMY on a
  bonus is withheld (it is taxed on an annualised basis — a straight proportion of
  the monthly FMY is only an estimate).

## Proposed design (agreed in principle, not yet approved to build)

- An employee's **payroll recurrence also generates the three bonus payments** as
  extra managed rows of the same recurrence — no new screen, no hand entry:
    - Easter → Holy Wednesday of each year (compute Orthodox Easter; move to a
      working day if needed).
    - Christmas → 21 December (previous working day if off).
    - Leave allowance → a month Angel chooses (a setting, or per recurrence).
- **Amounts** from the payroll period in force on the reference date, scaled by the
  bonus factor (Easter ½, Christmas 1, × 1,04166 if confirmed; leave ½) and pro-rated
  for partial accrual periods (recurrence start/end inside the period). Scale Net,
  FMY and EFKA ee/er alike — an estimate; the exact payslip figure is set when the
  row is reconciled, like a salary.
- **Keys:** give bonus rows their own slot keys (e.g.
  `recurrence:{id}:bonus-easter:{year}`) so they never collide with monthly slots and
  survive date moves, mirroring how `paymentDate` keeps keys stable.
- They should show on the Recurring page (Payroll group) and feed FMY/EFKA through
  `TaxSync` with no extra work.
- Only **employees** (payroll recurrences) — never contractors.

## Open questions for Angel

1. Leave allowance: which month (or per employee)?
2. Use the ×1,04166 factor if it's confirmed to apply?
3. Should bonus rows be labelled distinctly (e.g. description "Christmas bonus") —
   presumably yes.

## Tests the build must have

Money and dates always get tests (`~/.claude/CLAUDE.md`): Orthodox Easter dates for
several years, Holy Wednesday/21 Dec working-day moves, full vs pro-rated amounts,
partial start/end years, reconciled bonus rows frozen, bonus FMY/EFKA reaching the
tax ledgers.

## Sources

- [taxheaven: ΓΣΕΕ application for calculating the Easter bonus](https://www.taxheaven.gr/news/67299/gsee-diktyakh-efarmogh-ypologismoy-katabolhs-dwroy-pasxa)
- [taxheaven: Christmas bonus — calculation and payment](https://www.taxheaven.gr/news/65881/dwro-xristoygennwn-ypologismos-kai-katabolh-toy)
- [odigostoupoliti: Easter bonus 2026 — when and on which salary](https://www.odigostoupoliti.eu/doro-pascha-2026-pote-plironetai-kai-me-poio-mistho-ypologizetai/amp/)
- [odigostoupoliti: Easter bonus 2026 — April raise not counted](https://www.odigostoupoliti.eu/doro-pascha-2026-gia-ton-ypologismo-den-lamvanetai-ypopsi-afksisi-tou-misthou/amp/)
- [taxheaven: ΚΕΠΕΑ/ΓΣΕΕ on paying the Easter bonus](https://www.taxheaven.gr/news/70479/kepeagsee-katabolh-dwroy-pasxa)
