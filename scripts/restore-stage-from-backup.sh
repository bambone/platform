#!/usr/bin/env bash
# Stage/local DB restore: rclone latest .sql.zst → temp MySQL DB → copy whitelist tables.
# Guards run before download. Whitelist: scripts/restore/restore-include.txt
#
# Env: CONFIRM_STAGE_RESTORE=yes (required)
#      RENTBASE_RESTORE_RCLONE_REMOTE (e.g. mailru-webdav:Backups/rentbase/mysql)
#      RCLONE_CONFIG (optional)
#
# Usage:
#   CONFIRM_STAGE_RESTORE=yes RENTBASE_RESTORE_RCLONE_REMOTE='remote:path' \
#     ./scripts/restore-stage-from-backup.sh
#
#   ./scripts/restore-stage-from-backup.sh --sql-path /path/to/dump.sql.zst
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RESTORE_DIR="$ROOT/scripts/restore"
INCLUDE_FILE="$RESTORE_DIR/restore-include.txt"
SNAP_DIR="$RESTORE_DIR/snapshots"
SRC_DB="${SRC_DB:-platform_restore_src}"

SKIP_SNAPSHOT=0
ALLOW_ABSOLUTE_REDIRECTS=0
SQL_PATH=""
DOWNLOAD_DIR=""
KEEP_DUMP=0
RCLONE_REMOTE="${RENTBASE_RESTORE_RCLONE_REMOTE:-}"
RCLONE_CFG="${RCLONE_CONFIG:-}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --skip-snapshot) SKIP_SNAPSHOT=1; shift ;;
    --allow-absolute-redirects) ALLOW_ABSOLUTE_REDIRECTS=1; shift ;;
    --sql-path=*)
      SQL_PATH="${1#*=}"
      shift
      ;;
    --src-db=*)
      SRC_DB="${1#*=}"
      shift
      ;;
    --download-dir=*)
      DOWNLOAD_DIR="${1#*=}"
      shift
      ;;
    --keep-downloaded-dump) KEEP_DUMP=1; shift ;;
    --rclone-remote=*)
      RCLONE_REMOTE="${1#*=}"
      shift
      ;;
    --help|-h)
      grep '^#' "$0" | head -n 25
      exit 0
      ;;
    *)
      echo "Unknown arg: $1" >&2
      exit 1
      ;;
  esac
done

die() { echo "error: $*" >&2; exit 1; }

# Minimal .env parser: export KEY=VAL for requested keys only
load_env_kv() {
  local env_file="$1"
  [[ -f "$env_file" ]] || die ".env not found: $env_file"
  APP_ENV=""
  DB_HOST=""
  DB_PORT="3306"
  DB_DATABASE=""
  DB_USERNAME=""
  DB_PASSWORD=""
  while IFS= read -r line || [[ -n "$line" ]]; do
    line="${line#"${line%%[![:space:]]*}"}"
    [[ -z "$line" || "$line" == \#* ]] && continue
    [[ "$line" != *=* ]] && continue
    local key="${line%%=*}"
    key="${key%"${key##*[![:space:]]}"}"
    local val="${line#*=}"
    val="${val#"${val%%[![:space:]]*}"}"
    if [[ "$val" == \"*\" ]]; then
      val="${val:1:${#val}-2}"
      val="${val//\\n/$'\n'}"
    elif [[ "$val" == \'*\' ]]; then
      val="${val:1:${#val}-2}"
    else
      val="${val%%[[:space:]]#*}"
    fi
    case "$key" in
      APP_ENV) APP_ENV="$val" ;;
      DB_HOST) DB_HOST="$val" ;;
      DB_PORT) DB_PORT="$val" ;;
      DB_DATABASE) DB_DATABASE="$val" ;;
      DB_USERNAME) DB_USERNAME="$val" ;;
      DB_PASSWORD) DB_PASSWORD="$val" ;;
    esac
  done <"$env_file"
}

normalize_host_ok() {
  local h="${1,,}"
  [[ "$h" == "localhost" || "$h" == "::1" ]] && return 0
  [[ "$h" == 127.* ]] && return 0
  return 1
}

load_env_kv "$ROOT/.env"

[[ "${APP_ENV,,}" != "production" ]] || die "APP_ENV is production"
[[ -n "$DB_DATABASE" ]] || die "DB_DATABASE empty in .env"
DB_HOST="${DB_HOST:-127.0.0.1}"
normalize_host_ok "$DB_HOST" || die "DB_HOST must be 127.0.0.1, localhost, or ::1 (got $DB_HOST)"

[[ "${CONFIRM_STAGE_RESTORE:-}" == "yes" ]] || die "set CONFIRM_STAGE_RESTORE=yes"

[[ -f "$INCLUDE_FILE" ]] || die "missing $INCLUDE_FILE"

mapfile -t TABLES < <(grep -v '^[[:space:]]*$\|^[[:space:]]*#' "$INCLUDE_FILE" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
[[ "${#TABLES[@]}" -gt 0 ]] || die "no tables in $INCLUDE_FILE"

CNF="$(mktemp)"
copy_sql=""
trap "rm -f \"$CNF\"" EXIT
chmod 600 "$CNF"
{
  echo '[client]'
  echo "user=$DB_USERNAME"
  echo "password=$DB_PASSWORD"
  echo "host=$DB_HOST"
  echo "port=$DB_PORT"
} >"$CNF"

if [[ "$SKIP_SNAPSHOT" -eq 0 ]]; then
  mkdir -p "$SNAP_DIR"
  stamp="$(date +%Y%m%d_%H%M%S)"
  snap="$SNAP_DIR/${DB_DATABASE}_pre_restore_${stamp}.sql"
  echo "Snapshot -> $snap"
  mysqldump --defaults-extra-file="$CNF" --single-transaction --no-tablespaces "$DB_DATABASE" >"$snap"
fi

DUMP_LOCAL=""
USE_ZSTD=0
if [[ -n "$SQL_PATH" ]]; then
  [[ -f "$SQL_PATH" ]] || die "SqlPath not found: $SQL_PATH"
  DUMP_LOCAL="$(realpath "$SQL_PATH")"
  [[ "$DUMP_LOCAL" == *.zst ]] && USE_ZSTD=1
else
  [[ -n "$RCLONE_REMOTE" ]] || die "set RENTBASE_RESTORE_RCLONE_REMOTE or pass --sql-path="
  cache="${DOWNLOAD_DIR:-${TMPDIR:-/tmp}/rentbase-restore}"
  mkdir -p "$cache"
  remote="${RCLONE_REMOTE%/}"
  rcbase=()
  [[ -n "$RCLONE_CFG" ]] && rcbase+=(--config "$RCLONE_CFG")
  echo "rclone lsf $remote ..."
  tmp_ls="$(mktemp)"
  rclone "${rcbase[@]}" lsf --files-only "$remote" >"$tmp_ls" || die "rclone lsf failed"
  mapfile -t all_files <"$tmp_ls"
  rm -f "$tmp_ls"
  zst_files=()
  for f in "${all_files[@]}"; do
    [[ "$f" =~ \.(sql\.zst|zst)$ ]] && zst_files+=("$f")
  done
  [[ "${#zst_files[@]}" -gt 0 ]] || die "no .zst under $remote"
  latest="$(printf '%s\n' "${zst_files[@]}" | sort | tail -n 1)"
  DUMP_LOCAL="$cache/$latest"
  echo "rclone copyto $remote/$latest -> $DUMP_LOCAL"
  rclone "${rcbase[@]}" copyto "$remote/$latest" "$DUMP_LOCAL"
  USE_ZSTD=1
fi

echo "Recreate temporary database [$SRC_DB]..."
mysql --defaults-extra-file="$CNF" -e "DROP DATABASE IF EXISTS \`$SRC_DB\`; CREATE DATABASE \`$SRC_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "Importing dump (may take a while)..."
if [[ "$USE_ZSTD" -eq 1 ]]; then
  zstd -dc "$DUMP_LOCAL" | mysql --defaults-extra-file="$CNF" "$SRC_DB"
else
  mysql --defaults-extra-file="$CNF" "$SRC_DB" <"$DUMP_LOCAL"
fi

table_in_schema() {
  local sch="$1" t="$2" c
  sch="${sch//\'/\'\'}"
  t="${t//\'/\'\'}"
  c="$(mysql --defaults-extra-file="$CNF" -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$sch' AND table_name='$t';")"
  c="${c//[[:space:]]/}"
  [[ "$c" == 1 ]]
}

for t in "${TABLES[@]}"; do
  table_in_schema "$SRC_DB" "$t" || die "fail-fast: table $t missing in $SRC_DB"
  table_in_schema "$DB_DATABASE" "$t" || die "fail-fast: table $t missing in $DB_DATABASE (run migrations?)"
done

copy_sql="$(mktemp)"
trap "rm -f \"$CNF\" \"$copy_sql\"" EXIT
{
  echo 'SET FOREIGN_KEY_CHECKS=0;'
  for t in "${TABLES[@]}"; do
    printf 'DELETE FROM `%s`.`%s`;\n' "$DB_DATABASE" "$t"
  done
  for t in "${TABLES[@]}"; do
    printf 'INSERT INTO `%s`.`%s` SELECT * FROM `%s`.`%s`;\n' "$DB_DATABASE" "$t" "$SRC_DB" "$t"
  done
  echo 'SET FOREIGN_KEY_CHECKS=1;'
} >"$copy_sql"

echo "Copying ${#TABLES[@]} tables into [$DB_DATABASE]..."
mysql --defaults-extra-file="$CNF" <"$copy_sql"
rm -f "$copy_sql"

if printf '%s\n' "${TABLES[@]}" | grep -qx 'redirects'; then
  rcnt="$(mysql --defaults-extra-file="$CNF" -N -B -e "SELECT COUNT(*) FROM \`$DB_DATABASE\`.\`redirects\` WHERE \`from_url\` REGEXP '^https?://' OR \`to_url\` REGEXP '^https?://' LIMIT 1;")"
  rcnt="${rcnt//[[:space:]]/}"
  if [[ "${rcnt:-0}" -gt 0 && "$ALLOW_ABSOLUTE_REDIRECTS" -eq 0 ]]; then
    die "redirects contains absolute http(s) URLs; use --allow-absolute-redirects to bypass"
  fi
  if [[ "${rcnt:-0}" -gt 0 ]]; then
    echo "warning: redirects contains absolute http(s) URLs (--allow-absolute-redirects set)." >&2
  fi
fi

echo "DROP temporary database [$SRC_DB]..."
mysql --defaults-extra-file="$CNF" -e "DROP DATABASE IF EXISTS \`$SRC_DB\`;"

if [[ -z "$SQL_PATH" && "$KEEP_DUMP" -eq 0 && -n "$DUMP_LOCAL" && -f "$DUMP_LOCAL" ]]; then
  rm -f "$DUMP_LOCAL"
fi

cd "$ROOT"
echo "php artisan optimize:clear"
php artisan optimize:clear --no-interaction
echo "php artisan migrate --force"
php artisan migrate --force --no-interaction
