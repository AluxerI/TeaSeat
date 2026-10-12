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
BACKUP_ROOT="${BACKUP_ROOT:-backups/production}"
TIMESTAMP="$(date -u +'%Y%m%dT%H%M%SZ')"
BACKUP_DIR="${BACKUP_ROOT}/${TIMESTAMP}"

if [[ ! -f "${COMPOSE_FILE}" ]]; then
    echo "Compose file not found: ${COMPOSE_FILE}" >&2
    exit 1
fi

if [[ ! -f "${ENV_FILE}" ]]; then
    echo "Environment file not found: ${ENV_FILE}" >&2
    exit 1
fi

mkdir -p "${BACKUP_DIR}"

compose=(docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}")

echo "Creating PostgreSQL backup..."
"${compose[@]}" exec -T pgsql sh -ec \
    'pg_dump --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --format=custom --compress=9 --no-owner --no-privileges' \
    > "${BACKUP_DIR}/database.dump"

echo "Creating public storage backup..."
"${compose[@]}" run --rm --no-deps -T --entrypoint sh backend -ec \
    'tar -C /app/backend/storage/app/public -czf - .' \
    > "${BACKUP_DIR}/public-storage.tar.gz"

echo "Validating PostgreSQL archive..."
"${compose[@]}" exec -T pgsql pg_restore --list \
    < "${BACKUP_DIR}/database.dump" \
    > /dev/null

{
    echo "created_at_utc=${TIMESTAMP}"
    echo "git_commit=$(git rev-parse HEAD 2>/dev/null || echo unknown)"
    echo "compose_project=teaseat-production"
    echo "database_format=postgresql-custom"
} > "${BACKUP_DIR}/metadata.txt"

(
    cd "${BACKUP_DIR}"
    sha256sum database.dump public-storage.tar.gz metadata.txt > SHA256SUMS
)

echo "Backup created: ${BACKUP_DIR}"
du -h "${BACKUP_DIR}/database.dump" "${BACKUP_DIR}/public-storage.tar.gz"
