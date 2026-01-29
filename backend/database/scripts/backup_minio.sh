#!/bin/bash

# MinIO Backup Script
# Usage: ./backup_minio.sh [backup_dir]

BACKUP_DIR=${1:-/backups/minio}
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
MINIO_ENDPOINT=${MINIO_ENDPOINT:-http://minio:9000}
MINIO_ACCESS_KEY=${MINIO_ROOT_USER:-minioadmin}
MINIO_SECRET_KEY=${MINIO_ROOT_PASSWORD:-minioadmin}
BUCKET=${AWS_BUCKET:-laravel}

mkdir -p "$BACKUP_DIR"

# Sync bucket to backup directory
mc alias set backup "$MINIO_ENDPOINT" "$MINIO_ACCESS_KEY" "$MINIO_SECRET_KEY"
mc mirror "backup/$BUCKET" "$BACKUP_DIR/backup_${TIMESTAMP}"

# Create archive
tar -czf "$BACKUP_DIR/backup_${TIMESTAMP}.tar.gz" -C "$BACKUP_DIR" "backup_${TIMESTAMP}"
rm -rf "$BACKUP_DIR/backup_${TIMESTAMP}"

# Keep only last 30 days
find "$BACKUP_DIR" -name "backup_*.tar.gz" -mtime +30 -delete

echo "Backup created: backup_${TIMESTAMP}.tar.gz"


