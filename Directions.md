# Directions

Standing directions for every session working on Yolt-App (the Laravel app in this
repo, `Angel-Pappas/yolt-app-new`). **The shared ways of working in
`~/.claude/CLAUDE.md` apply here in full** (quality directive, design principles,
screenshots, docs pattern, ship workflow, safety, efficiency). This file holds only
what is specific to Yolt. Read all of it and never skip applying any.

Direction 1: **This is the live app on real company data.** Yolt-App was rebuilt from
an old Next.js/Supabase app onto Laravel; the data cutover ran and was verified clean
on 2026-09-09. The old app (`Angel-Pappas/yolt-app`) was retired and its repo deleted
— there is nothing to fall back to. Current behaviour: `Summary.md`; what changed and
why: `History.md`; what's next: `ROADMAP.md`; code patterns and commands: `AGENTS.md`.

Direction 2: **The data must never be lost.** Work and test only in the dev checkout
(`/home/ploi/yolt-app-dev`, SQLite); the live folder
(`/home/ploi/yolt-app.pappas.yoltobots.click`) is touched only by `./deploy.sh`, which
refuses to run outside production and backs the database up first. Never run tests,
seeders (including `DemoSeeder`), `migrate:fresh`/`db:wipe`/`migrate:rollback`, or
ad-hoc writes against the live database. Any migration that drops or rewrites data is
raised with Angel before it ships.

Direction 3: **Ship every finished change to `main` and live** with `./ship.sh` from
the dev checkout (full check incl. frontend unit tests → commit → push → CI → deploy).
Even if a session starts on a branch, carry the work through to `main` yourself; no
pull requests. If a push is genuinely blocked (e.g. no GitHub access), say plainly
what unblocks it — the only acceptable reason to stop short.

Direction 4: **Look before shipping:** `pnpm run build` then
`pnpm run shots <path> […]` (`--mobile`, `--dark`) on the dev app's fake `DemoSeeder`
data; review the PNGs against the design principles.

Direction 5: **Tests:** Pest for the server, `pnpm run test` (vp test) for pure
frontend logic in `resources/js/lib/*`. Money/tax maths and authorization always get
tests; the client's amount maths (`lib/amounts.ts`) must stay in lockstep with the
server's `App\Support\AmountLines`.

Direction 6: **Width in dialogs:** when composing utility classes, check the computed
width — a shared base class that already sets `w-full` can silently win over a width
appended after it (cascade order isn't the order classes appear in the string).

We will add more to these as we move on with the app.
