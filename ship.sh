#!/usr/bin/env bash
# Ship a change from the DEV checkout to the live app in one go:
#   auto-format → full check (exactly what CI runs) → commit everything → push to
#   main → wait for that commit's CI → deploy. Stops at the first failure (nothing is
#   pushed if a check fails; nothing is deployed if CI fails). From the dev folder:
#
#   ./ship.sh "Commit subject" ["Optional commit body"]
set -euo pipefail
cd "$(dirname "$0")"

LIVE=/home/ploi/yolt-app.pappas.yoltobots.click
REPO=Angel-Pappas/yolt-app-new
subject=${1:?usage: ./ship.sh "Commit subject" ["body"]}
body=${2:-}

[ "$(pwd)" != "$LIVE" ] || { echo "Run ship.sh from the dev checkout, not the live folder." >&2; exit 1; }
[ "$(git rev-parse --abbrev-ref HEAD)" = main ] || { echo "Not on main." >&2; exit 1; }

step() { printf '\n==> %s  (+%ss)\n' "$1" "$SECONDS"; }

step "Auto-format"; pnpm run check:fix; vendor/bin/pint --parallel
step "Build"; pnpm run build
step "Full check (composer ci:check)"; composer ci:check

step "Commit + push"
git add -A
git diff --cached --quiet && { echo "Nothing to commit." >&2; exit 1; }
msg="$subject"
[ -n "$body" ] && msg="$msg

$body"
git commit -q -m "$msg

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
sha=$(git rev-parse HEAD)
git push -q origin main

step "Waiting for CI"
run=""
for _ in $(seq 1 30); do
    run=$(gh run list --repo "$REPO" --commit "$sha" --json databaseId -q '.[0].databaseId' 2>/dev/null || true)
    [ -n "$run" ] && break
    sleep 3
done
[ -n "$run" ] || { echo "CI run for $sha not found." >&2; exit 1; }
gh run watch --repo "$REPO" "$run" --exit-status >/dev/null \
    || { echo "CI failed: https://github.com/$REPO/actions/runs/$run" >&2; exit 1; }
echo "CI green."

step "Deploy"
"$LIVE/deploy.sh"
echo "Shipped in ${SECONDS}s."
