# Directions

Standing directions for every session working on Yolt-App (the Laravel app in this
repo, `Angel-Pappas/yolt-app-new`). Read all of them and never skip applying any.

Direction 1: Always read all directions below and never skip reading or applying any
of these.

Direction 2: Always commit and push to `main` when you are done with your work.
"Done" means the change is actually live for the user: written → committed → **on
`main`** → deployed to the live VM with `./deploy.sh` (see the **Workflow** in
`AGENTS.md` — nothing deploys automatically on push). This holds even
when a session is started on a feature branch: carry the work through to `main`
yourself (merge the feature branch into `main` and push it) rather than stopping at
the branch and asking the user to merge. The user does not know how to
commit/push/merge/PR and has explicitly delegated all of that; never leave a finished
change sitting on a branch or hand the user a git step to perform. Do NOT open a pull
request for this — merge to `main` directly. **Keep CI green**: run the full local
verify from `AGENTS.md` (starting with `pnpm run check:fix`) before every push. (If a
push is genuinely blocked, e.g. missing GitHub write access, say so plainly and what
unblocks it — that's the only acceptable reason to stop short of `main`.)

Direction 3: Verify the code (build, lint, typecheck, tests all pass), then LOOK at
what you changed with the fast screenshot tool — never by driving a browser by hand
(that was slow and is why manual visual checks were stopped early on). In the dev
checkout run `pnpm run shots <path> [<path>…]` (add `--mobile` / `--dark` when
relevant); it screenshots pages of the dev app on fake `DemoSeeder` data in seconds,
and you read the PNGs and review them against Direction 10 before shipping. It never
touches the live site or live data. The user still does their own functional check —
don't keep restating that, and don't tack "please verify visually" caveats onto
replies.

Direction 4: Keep `Summary.md` and the progress log in `Stack Change Plan.md` current
— update the relevant section whenever a feature is added, removed, or changed.

Direction 5: Never let a modal (or any other fixed-width container) scroll
horizontally as a side effect of something you add to it. Keep new fields/controls
within whatever width the container currently has — use smaller variants, wrap to a
new row, shrink text, whatever it takes — rather than letting content silently
overflow sideways. Double-check that composed utility classes actually produce the
width you intend (a shared base class that already sets `w-full` can silently win
over a width you append after it, since the cascade order isn't the order classes
appear in the string) — don't just eyeball the JSX, verify the computed width makes
sense. If something genuinely needs more room than the container currently has, stop
and ask before widening it — don't widen a container unilaterally as a side effect of
adding something else to it.

Direction 6: Always assume your memory is wrong and verify. Never state a version, an
API, a library detail, or "the right/latest way to do something" from memory — look
it up against official/current sources first (the docs, then a web search when the
docs are silent), find the latest and most proper way, then document it properly and
consult your own documentation before re-searching. Treat any version/API/best-
practice claim made without a lookup as a bug.

Direction 7: Always check current best practices and deduce whether the thing you
just built would benefit from a test, and whether writing one is in the best
practices of the field. If yes, write it (Pest). In new work write tests for every
single thing where a test makes sense from a developer's perspective and per best
practices — especially money/tax math and authorization.

Direction 8: **The stack change is complete — this is the live app.** Yolt-App was
rebuilt from the old Next.js/Supabase app onto Laravel, and the data cutover ran and
was verified clean on 2026-09-09. The Laravel app in this repo (`yolt-app-new`, on
our own VM) is now the live app on the real data. The full history, every
decision, and the progress log are in **`Stack Change Plan.md`**; the app's feature
behavior is documented in **`Summary.md`**; the established code patterns and the
exact local verify workflow are in **`AGENTS.md`** — read those to get up to speed.
Build new work in small, tested slices, each pushed to `main` and deployed with
`./deploy.sh`, with CI kept green. The **old app** (`Angel-Pappas/yolt-app`, on
Vercel + Supabase) has been **retired and its GitHub repo deleted (2026-09)** — it no
longer exists, so there is nothing to fall back to or "not work in"; this Laravel app
is the only app.

Direction 9: **The data in the live app is real company data and must never be lost.**
Never run tests, `migrate:fresh`/`db:wipe`/`migrate:rollback`, seeders, or ad-hoc
writes against the live database. Work and test only in the dev checkout
(`/home/ploi/yolt-app-dev`, SQLite); the live folder
(`/home/ploi/yolt-app.pappas.yoltobots.click`) is touched only by `./deploy.sh`,
which backs the database up first. Any migration that drops or rewrites data must be
raised with the user before it ships.

Direction 10: **Design principles — the user's taste, written down.** Things that
feel obviously "logical" to the user must never need pointing out. Apply all of these
to every change, and whenever the user catches something "weirdly made", add the
underlying principle here (not just the fix) so it never recurs:

- **Never show the same information twice on one screen** (e.g. a row count both in a
  table's totals row and under the table).
- **Every element must earn its space.** Screen height is precious, especially on
  lists — no redundant lines, labels, gaps or chrome.
- **Changing a shared component means reviewing everything it renders**, not just the
  part being changed: look at the whole composed result on each page that uses it.
- **Fewer controls, fewer clicks.** Don't add a second control that does what an
  existing one already does; the common action should be the shortest path.
- **Consistency:** the same thing looks and behaves the same everywhere (e.g. every
  table column has a filter, every list uses `DataTable`). A deviation must be
  deliberate and called out to the user.
- **Keep state the user chose.** A view/selection the user picked (a balance view, a
  filter, a date range) must survive other actions on the page unless they clear it.
- **Self-review before shipping:** for each page the change affects, take screenshots
  (Direction 3) and write down for yourself "what the user now sees here", then remove
  anything redundant, confusing, wasteful or inconsistent before pushing.

Direction 11: **Efficiency.** Keep the edit → verify → ship loop fast: batch
independent commands, run the full verify once (not piecemeal), screenshot only the
affected pages, and suggest to the user any tooling or process change that would
save time.

We will add more to these as we move on with the app.
