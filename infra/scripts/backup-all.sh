#!/bin/bash
set -euo pipefail

# Main backup script that runs all backups
# Usage: backup-all.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "=========================================="
echo "Starting backup process: $(date)"
echo "=========================================="

# Run PostgreSQL backup
echo ""
echo "--- PostgreSQL Backup ---"
"${SCRIPT_DIR}/backup-postgres.sh" || {
    echo "ERROR: PostgreSQL backup failed" >&2
    exit 1
}

# Run MinIO backup
echo ""
echo "--- MinIO Backup ---"
"${SCRIPT_DIR}/backup-minio.sh" || {
    echo "ERROR: MinIO backup failed" >&2
    exit 1
}

echo ""
echo "=========================================="
echo "Backup process completed: $(date)"
echo "=========================================="

