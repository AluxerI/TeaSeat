#!/usr/bin/env bash
set -Eeuo pipefail

umask 077

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd -- "${SCRIPT_DIR}/../.." && pwd)"

cd "${REPO_ROOT}"

case "$(uname -s)" in
    MINGW*|MSYS*) export MSYS_NO_PATHCONV=1 ;;
esac

COMPOSE_FILE="docker-compose.production.yml"
ENV_FILE="backend/.env.production"
BACKUP_DIR="${1:-}"

if [[ -z "${BACKUP_DIR}" ]]; then
    echo "Usage: CONFIRM_RESTORE=RESTORE_TEASEAT $0 backups/production/<timestamp>" >&2
    exit 1
fi

if [[ "${CONFIRM_RESTORE:-}" != "RESTORE_TEASEAT" ]]; then
    echo "Restore is destructive. Set CONFIRM_RESTORE=RESTORE_TEASEAT to continue." >&2
    exit 1
fi

if [[ ! -d "${BACKUP_DIR}" ]]; then
    echo "Backup directory not found: ${BACKUP_DIR}" >&2
    exit 1
fi

for file in database.dump public-storage.tar.gz metadata.txt SHA256SUMS; do
    if [[ ! -f "${BACKUP_DIR}/${file}" ]]; then
        echo "Required backup file not found: ${BACKUP_DIR}/${file}" >&2
        exit 1
    fi
done

(
    cd "${BACKUP_DIR}"
    sha256sum --check SHA256SUMS
)

compose=(docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}")

echo "Stopping application services..."
"${compose[@]}" stop gateway backend worker

echo "Recreating the application database..."
"${compose[@]}" exec -T pgsql sh -ec \
    'dropdb --if-exists --force --maintenance-db=postgres --username="$POSTGRES_USER" "$POSTGRES_DB" && createdb --maintenance-db=postgres --username="$POSTGRES_USER" "$POSTGRES_DB"'

echo "Restoring PostgreSQL backup..."
"${compose[@]}" exec -T pgsql sh -ec \
    'pg_restore --exit-on-error --no-owner --no-privileges --username="$POSTGRES_USER" --dbname="$POSTGRES_DB"' \
    < "${BACKUP_DIR}/database.dump"

echo "Replacing public storage..."
"${compose[@]}" run --rm --no-deps -T --entrypoint sh backend -ec \
    'find /app/backend/storage/app/public -mindepth 1 -maxdepth 1 -exec rm -rf -- {} + && tar -C /app/backend/storage/app/public -xzf -' \
    < "${BACKUP_DIR}/public-storage.tar.gz"

echo "Applying any newer migrations..."
"${compose[@]}" run --rm backend php artisan migrate --force

echo "Starting application services..."
"${compose[@]}" up -d --wait backend worker gateway

echo "Restore completed from: ${BACKUP_DIR}"
