#!/bin/bash
set -euo pipefail

# PostgreSQL backup script
# Usage: backup-postgres.sh

BACKUP_DIR="${BACKUP_DIR:-/backups/postgres}"
DATE=$(date +%Y-%m-%d)
BACKUP_FILE="${BACKUP_DIR}/${DATE}.sql.gz"

# Ensure backup directory exists
mkdir -p "${BACKUP_DIR}"

# Database connection parameters
DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_DATABASE:-laravel}"
DB_USER="${DB_USERNAME:-laravel}"
DB_PASSWORD="${DB_PASSWORD:-password}"

# Export password for pg_dump
export PGPASSWORD="${DB_PASSWORD}"

# Create backup
echo "Starting PostgreSQL backup: ${BACKUP_FILE}"
pg_dump -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${DB_NAME}" \
    --no-owner --no-acl \
    | gzip > "${BACKUP_FILE}"

# Verify backup was created and is not empty
if [ ! -f "${BACKUP_FILE}" ] || [ ! -s "${BACKUP_FILE}" ]; then
    echo "ERROR: Backup file is missing or empty: ${BACKUP_FILE}" >&2
    exit 1
fi

# Get backup size
BACKUP_SIZE=$(du -h "${BACKUP_FILE}" | cut -f1)
echo "PostgreSQL backup completed: ${BACKUP_FILE} (${BACKUP_SIZE})"

# Cleanup old backups (keep 14 days)
echo "Cleaning up old PostgreSQL backups (keeping 14 days)..."
find "${BACKUP_DIR}" -name "*.sql.gz" -type f -mtime +14 -delete
echo "PostgreSQL backup cleanup completed"

