#!/usr/bin/env bash
# Re-copy the LIVE app's data into the "next" copy site's own database, wiping what
# the copy had. Run from the copy site's folder (/home/ploi/yolt-next/site).
# The live database is only READ (mysqldump); every write goes through the copy's
# own MySQL user, which has no rights on the live database.
set -euo pipefail
cd "$(dirname "$0")"

LIVE=/home/ploi/yolt-app.pappas.yoltobots.click
LIVE_DB=yolt_app
COPY_DB=yolt_next

cnf() { # cnf <app folder> <out file> → writes a mysql client file, prints the db name
    (cd "$1" && php -r '
        require "vendor/autoload.php";
        $e = Dotenv\Dotenv::createArrayBacked(getcwd())->load();
        printf("[client]\nhost=%s\nport=%s\nuser=%s\npassword=\"%s\"\n",
            $e["DB_HOST"] ?? "127.0.0.1", $e["DB_PORT"] ?? "3306", $e["DB_USERNAME"],
            addcslashes($e["DB_PASSWORD"] ?? "", "\\\""));
        file_put_contents("php://stderr", $e["DB_DATABASE"]);
    ' 2>"$2.db" >"$2")
    cat "$2.db"; rm -f "$2.db"
}

TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT; chmod 700 "$TMP"
[ "$(pwd)" != "$LIVE" ] || { echo "Refusing: this is the live folder." >&2; exit 1; }
copy_db="$(cnf "$(pwd)" "$TMP/copy.cnf")"
live_db="$(cnf "$LIVE" "$TMP/live.cnf")"
[ "$copy_db" = "$COPY_DB" ] || { echo "Refusing: this folder's database is '$copy_db', not '$COPY_DB'." >&2; exit 1; }
[ "$live_db" = "$LIVE_DB" ] || { echo "Refusing: live database is '$live_db', not '$LIVE_DB'." >&2; exit 1; }

echo "Dumping $LIVE_DB (read-only)…"
mysqldump --defaults-extra-file="$TMP/live.cnf" --single-transaction --routines --triggers \
    --no-tablespaces "$LIVE_DB" >"$TMP/dump.sql"
tail -n 1 "$TMP/dump.sql" | grep -q "Dump completed" || { echo "Dump incomplete." >&2; exit 1; }

echo "Replacing $COPY_DB…"
mysql --defaults-extra-file="$TMP/copy.cnf" -e "DROP DATABASE IF EXISTS \`$COPY_DB\`; CREATE DATABASE \`$COPY_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql --defaults-extra-file="$TMP/copy.cnf" "$COPY_DB" <"$TMP/dump.sql"
mysql --defaults-extra-file="$TMP/copy.cnf" "$COPY_DB" -e "TRUNCATE TABLE sessions;"

php artisan migrate --force
php artisan optimize:clear >/dev/null
php artisan managed:sync
php artisan optimize >/dev/null
echo "Copy refreshed from live: $(date '+%Y-%m-%d %H:%M')"
