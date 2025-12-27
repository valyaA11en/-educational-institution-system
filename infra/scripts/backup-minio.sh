#!/bin/bash
set -euo pipefail

# MinIO backup script using mc mirror
# Usage: backup-minio.sh

BACKUP_DIR="${BACKUP_DIR:-/backups/minio}"
DATE=$(date +%Y-%m-%d)
BACKUP_PATH="${BACKUP_DIR}/${DATE}"

# Ensure backup directory exists
mkdir -p "${BACKUP_PATH}"

# MinIO connection parameters
MINIO_ENDPOINT="${MINIO_ENDPOINT:-minio:9000}"
MINIO_ACCESS_KEY="${MINIO_ROOT_USER:-minioadmin}"
MINIO_SECRET_KEY="${MINIO_ROOT_PASSWORD:-minioadmin}"
MINIO_BUCKET="${MINIO_BUCKET:-pdo-files}"

# Configure mc alias
MC_ALIAS="backup-minio"
mc alias set "${MC_ALIAS}" "http://${MINIO_ENDPOINT}" "${MINIO_ACCESS_KEY}" "${MINIO_SECRET_KEY}" > /dev/null 2>&1 || true

# Check if bucket exists
if ! mc ls "${MC_ALIAS}/${MINIO_BUCKET}" > /dev/null 2>&1; then
    echo "WARNING: Bucket ${MINIO_BUCKET} does not exist, skipping backup"
    exit 0
fi

# Mirror bucket to local backup
echo "Starting MinIO backup: ${BACKUP_PATH}"
mc mirror --overwrite "${MC_ALIAS}/${MINIO_BUCKET}" "${BACKUP_PATH}" || {
    echo "ERROR: MinIO backup failed" >&2
    exit 1
}

# Verify backup was created
if [ ! -d "${BACKUP_PATH}" ] || [ -z "$(ls -A "${BACKUP_PATH}" 2>/dev/null)" ]; then
    echo "ERROR: Backup directory is empty: ${BACKUP_PATH}" >&2
    exit 1
fi

# Get backup size
BACKUP_SIZE=$(du -sh "${BACKUP_PATH}" | cut -f1)
echo "MinIO backup completed: ${BACKUP_PATH} (${BACKUP_SIZE})"

# Cleanup old backups (keep 14 days)
echo "Cleaning up old MinIO backups (keeping 14 days)..."
find "${BACKUP_DIR}" -type d -name "20[0-9][0-9]-[0-9][0-9]-[0-9][0-9]" -mtime +14 -exec rm -rf {} + 2>/dev/null || true
echo "MinIO backup cleanup completed"

