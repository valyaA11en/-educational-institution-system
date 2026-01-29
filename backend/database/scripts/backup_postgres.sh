#!/bin/bash

# PostgreSQL Backup Script
# Usage: ./backup_postgres.sh [backup_dir]

BACKUP_DIR=${1:-/backups/postgres}
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DB_NAME=${DB_DATABASE:-laravel}
DB_USER=${DB_USERNAME:-laravel}
DB_HOST=${DB_HOST:-postgres}

mkdir -p "$BACKUP_DIR"

# Create backup
PGPASSWORD="$DB_PASSWORD" pg_dump -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" \
    -F c -f "$BACKUP_DIR/backup_${TIMESTAMP}.dump"

# Compress
gzip "$BACKUP_DIR/backup_${TIMESTAMP}.dump"

# Keep only last 30 days
find "$BACKUP_DIR" -name "backup_*.dump.gz" -mtime +30 -delete

echo "Backup created: backup_${TIMESTAMP}.dump.gz"


