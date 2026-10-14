#!/usr/bin/env bash
# Ship a change from the DEV checkout to the live app in one go:
#   full local verify → commit everything → push to main → wait for CI → deploy.
# Stops at the first failure (nothing is pushed if verify fails; nothing is deployed
# if CI fails). Usage, from /home/ploi/yolt-app-dev:
#
#   ./ship.sh "Commit subject" ["Optional commit body"]
set -euo pipefail
cd "$(dirname "$0")"

LIVE=/home/ploi/yolt-app.pappas.yoltobots.click
REPO=Angel-Pappas/yolt-app-new
subject=${1:?usage: ./ship.sh "Commit subject" ["body"]}
body=${2:-}

if [ "$(pwd)" = "$LIVE" ]; then
    echo "ship.sh must run from the dev checkout, not the live folder." >&2
    exit 1
fi
if [ "$(git rev-parse --abbrev-ref HEAD)" != main ]; then
    echo "Not on main." >&2
    exit 1
fi

step() { printf '\n==> %s\n' "$1"; }

step "Format + lint"; pnpm run check:fix
step "Typecheck"; pnpm run types:check
step "Pint"; php vendor/bin/pint --parallel
step "PHPStan"; php vendor/bin/phpstan analyse --no-progress
step "Build"; pnpm run build
step "Tests"; php artisan test

step "Commit + push"
git add -A
if git diff --cached --quiet; then
    echo "Nothing to commit." >&2
    exit 1
fi
msg="$subject"
[ -n "$body" ] && msg="$msg

$body"
msg="$msg

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git commit -q -m "$msg"
sha=$(git rev-parse HEAD)
git push -q origin main

step "Waiting for CI"
run=""
for _ in $(seq 1 30); do
    run=$(gh run list --repo "$REPO" --commit "$sha" --workflow tests --json databaseId -q '.[0].databaseId' 2>/dev/null || true)
    [ -n "$run" ] && break
    sleep 3
done
[ -n "$run" ] || { echo "CI run for $sha not found." >&2; exit 1; }
gh run watch --repo "$REPO" "$run" --exit-status >/dev/null \
    || { echo "CI failed: https://github.com/$REPO/actions/runs/$run" >&2; exit 1; }
echo "CI green."

step "Deploy"
"$LIVE/deploy.sh"
