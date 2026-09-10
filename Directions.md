# Directions

Standing directions for every session working on Yolt-App (the Laravel app in this
repo, `Angel-Pappas/yolt-app-new`). Read all of them and never skip applying any.

Direction 1: Always read all directions below and never skip reading or applying any
of these.

Direction 2: Always commit and push to `main` when you are done with your work.
"Done" means the change is actually live for the user: written → committed → **on
`main`** → deployed (Laravel Cloud auto-deploys on push to `main`). This holds even
when a session is started on a feature branch: carry the work through to `main`
yourself (merge the feature branch into `main` and push it) rather than stopping at
the branch and asking the user to merge. The user does not know how to
commit/push/merge/PR and has explicitly delegated all of that; never leave a finished
change sitting on a branch or hand the user a git step to perform. Do NOT open a pull
request for this — merge to `main` directly. **Keep CI green**: run the full local
verify from `AGENTS.md` (starting with `pnpm run check:fix`) before every push. (If a
push is genuinely blocked, e.g. missing GitHub write access, say so plainly and what
unblocks it — that's the only acceptable reason to stop short of `main`.)

Direction 3: Don't visually check — make sure the code has no errors (build, lint,
typecheck, tests all pass), but leave the visual/functional check to the user. Do NOT
keep restating this in replies — the user knows the visual check is his and will do
it regardless. Just make the change and report that the code checks pass; never tack
on "I can't eyeball this / this lands on you to verify" caveats.

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
Laravel Cloud) is now the live app on the real data. The full history, every
decision, and the progress log are in **`Stack Change Plan.md`**; the app's feature
behavior is documented in **`Summary.md`**; the established code patterns and the
exact local verify workflow are in **`AGENTS.md`** — read those to get up to speed.
Build new work in small, tested slices, each pushed to `main` (auto-deploys to
Laravel Cloud) with CI kept green. The **old app** (`Angel-Pappas/yolt-app`, on
Vercel + Supabase) is a **read-only fallback pending retirement** — don't work in it.

We will add more to these as we move on with the app.
