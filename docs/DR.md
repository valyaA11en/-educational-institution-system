# Disaster Recovery (DR) Runbook

## Overview

This document describes the disaster recovery procedures for the PDO (Pedagogical Data Organization) system. The system includes automated daily backups of PostgreSQL database and MinIO object storage, with a 14-day retention policy.

## Backup System

### Architecture

The backup system consists of:
- **Backup Service**: A Docker container running cron that executes daily backups
- **PostgreSQL Backups**: Daily `pg_dump` exports compressed with gzip
- **MinIO Backups**: Daily `mc mirror` operations to copy all buckets
- **Retention**: 14 days (older backups are automatically deleted)

### Backup Schedule

- **Frequency**: Daily at 2:00 AM UTC
- **Location**: `/backups/postgres/` and `/backups/minio/`
- **Format**: 
  - PostgreSQL: `YYYY-MM-DD.sql.gz`
  - MinIO: `YYYY-MM-DD/` (directory)

### Backup Service

The backup service runs as a Docker container (`pdo_backup`) defined in `infra/docker-compose.yml`.

**Start the backup service:**
```bash
docker-compose -f infra/docker-compose.yml up -d backup
```

**View backup logs:**
```bash
docker logs -f pdo_backup
```

**Manually trigger backup:**
```bash
docker exec pdo_backup /usr/local/bin/backup-all.sh
```

## Backup Verification

### Automated Verification

Run the restore check script to verify a backup:

```bash
# Check latest backup
./infra/scripts/restore_check.sh

# Check specific backup
./infra/scripts/restore_check.sh 2024-01-15 /backups/postgres/2024-01-15.sql.gz
```

The script will:
1. Start a temporary PostgreSQL container
2. Restore the backup
3. Verify critical tables exist and contain data:
   - `tenants` table
   - `users` table
   - Other important tables (groups, subjects, rooms, schedule_items, assignments, documents)
4. Clean up the test container

**Expected output:**
```
✓ tenants table exists with X record(s)
✓ users table exists with X record(s)
✓ groups table exists
✓ subjects table exists
...
✓ Backup verification passed
```

### Manual Verification

**List available backups:**
```bash
# PostgreSQL backups
ls -lh /backups/postgres/

# MinIO backups
ls -lh /backups/minio/
```

**Check backup size:**
```bash
du -sh /backups/postgres/*
du -sh /backups/minio/*
```

**Verify backup integrity (PostgreSQL):**
```bash
gunzip -t /backups/postgres/2024-01-15.sql.gz
```

## Restore Procedures

### PostgreSQL Restore

**Prerequisites:**
- Backup file: `/backups/postgres/YYYY-MM-DD.sql.gz`
- Access to PostgreSQL container or database server
- Database credentials

**Steps:**

1. **Stop the application** (optional, recommended):
   ```bash
   docker-compose -f infra/docker-compose.yml stop php-fpm queue-worker
   ```

2. **Create a new database** (if restoring to a new environment):
   ```bash
   docker exec -it pdo_postgres psql -U laravel -c "CREATE DATABASE laravel_restore;"
   ```

3. **Restore the backup:**
   ```bash
   # Option 1: Restore to existing database
   gunzip -c /backups/postgres/2024-01-15.sql.gz | \
     docker exec -i pdo_postgres psql -U laravel -d laravel

   # Option 2: Restore to new database
   gunzip -c /backups/postgres/2024-01-15.sql.gz | \
     docker exec -i pdo_postgres psql -U laravel -d laravel_restore
   ```

4. **Verify restore:**
   ```bash
   docker exec -it pdo_postgres psql -U laravel -d laravel -c "SELECT COUNT(*) FROM tenants;"
   docker exec -it pdo_postgres psql -U laravel -d laravel -c "SELECT COUNT(*) FROM users;"
   ```

5. **Restart the application:**
   ```bash
   docker-compose -f infra/docker-compose.yml start php-fpm queue-worker
   ```

### MinIO Restore

**Prerequisites:**
- Backup directory: `/backups/minio/YYYY-MM-DD/`
- Access to MinIO container
- MinIO credentials

**Steps:**

1. **Configure MinIO client:**
   ```bash
   docker exec pdo_backup mc alias set restore-minio \
     http://minio:9000 \
     ${MINIO_ROOT_USER} \
     ${MINIO_ROOT_PASSWORD}
   ```

2. **Check if bucket exists:**
   ```bash
   docker exec pdo_backup mc ls restore-minio/
   ```

3. **Restore from backup:**
   ```bash
   # Option 1: Mirror backup to existing bucket (overwrites)
   docker exec pdo_backup mc mirror --overwrite \
     /backups/minio/2024-01-15 \
     restore-minio/pdo-files

   # Option 2: Create new bucket and restore
   docker exec pdo_backup mc mb restore-minio/pdo-files-restored
   docker exec pdo_backup mc mirror \
     /backups/minio/2024-01-15 \
     restore-minio/pdo-files-restored
   ```

4. **Verify restore:**
   ```bash
   docker exec pdo_backup mc ls restore-minio/pdo-files
   ```

## Disaster Recovery Scenarios

### Scenario 1: Database Corruption

**Symptoms:**
- Application errors related to database
- PostgreSQL container crashes
- Data inconsistencies

**Recovery Steps:**

1. **Stop affected services:**
   ```bash
   docker-compose -f infra/docker-compose.yml stop php-fpm queue-worker
   ```

2. **Identify last known good backup:**
   ```bash
   ls -lt /backups/postgres/ | head -5
   ```

3. **Verify backup integrity:**
   ```bash
   ./infra/scripts/restore_check.sh 2024-01-15 /backups/postgres/2024-01-15.sql.gz
   ```

4. **Restore database** (see PostgreSQL Restore section above)

5. **Restart services:**
   ```bash
   docker-compose -f infra/docker-compose.yml start php-fpm queue-worker
   ```

### Scenario 2: Complete System Failure

**Symptoms:**
- All containers down
- Infrastructure failure
- Need to rebuild from scratch

**Recovery Steps:**

1. **Set up new infrastructure:**
   ```bash
   # Copy backup files to new server
   scp -r /backups user@new-server:/backups

   # Start base services
   docker-compose -f infra/docker-compose.yml up -d postgres minio redis
   ```

2. **Wait for services to be ready:**
   ```bash
   docker-compose -f infra/docker-compose.yml ps
   ```

3. **Restore PostgreSQL** (see PostgreSQL Restore section)

4. **Restore MinIO** (see MinIO Restore section)

5. **Start application services:**
   ```bash
   docker-compose -f infra/docker-compose.yml up -d
   ```

6. **Run migrations** (if needed):
   ```bash
   docker exec pdo_php_fpm php artisan migrate
   ```

7. **Verify system:**
   ```bash
   # Check API health
   curl http://localhost:8000/api/health

   # Check database
   docker exec pdo_postgres psql -U laravel -d laravel -c "SELECT COUNT(*) FROM users;"
   ```

### Scenario 3: Partial Data Loss

**Symptoms:**
- Specific tables corrupted
- Recent data loss
- Need to restore specific date

**Recovery Steps:**

1. **Identify affected tables:**
   ```bash
   docker exec pdo_postgres psql -U laravel -d laravel -c "\dt"
   ```

2. **Choose appropriate backup date** (before data loss)

3. **Restore specific tables:**
   ```bash
   # Extract specific table from backup
   gunzip -c /backups/postgres/2024-01-15.sql.gz | \
     grep -A 10000 "CREATE TABLE public.users" | \
     docker exec -i pdo_postgres psql -U laravel -d laravel
   ```

4. **Verify data:**
   ```bash
   docker exec pdo_postgres psql -U laravel -d laravel -c "SELECT COUNT(*) FROM users;"
   ```

## Backup Monitoring

### Check Backup Status

**View recent backups:**
```bash
# PostgreSQL
ls -lht /backups/postgres/ | head -10

# MinIO
ls -lht /backups/minio/ | head -10
```

**Check backup service logs:**
```bash
docker logs --tail 100 pdo_backup
```

**Verify cron is running:**
```bash
docker exec pdo_backup crontab -l
```

### Alerting

Set up monitoring to alert on:
- Missing backups (no backup in last 24 hours)
- Backup failures (check logs for errors)
- Backup size anomalies (sudden size changes)
- Disk space (ensure `/backups` has sufficient space)

**Example monitoring script:**
```bash
#!/bin/bash
# Check if backup exists for today
TODAY=$(date +%Y-%m-%d)
if [ ! -f "/backups/postgres/${TODAY}.sql.gz" ]; then
    echo "ALERT: No backup found for ${TODAY}"
    exit 1
fi
```

## Maintenance

### Manual Backup

To create a manual backup outside of the scheduled time:

```bash
# Full backup
docker exec pdo_backup /usr/local/bin/backup-all.sh

# PostgreSQL only
docker exec pdo_backup /usr/local/bin/backup-postgres.sh

# MinIO only
docker exec pdo_backup /usr/local/bin/backup-minio.sh
```

### Cleanup Old Backups

Backups older than 14 days are automatically deleted. To manually clean up:

```bash
# PostgreSQL (older than 14 days)
find /backups/postgres -name "*.sql.gz" -mtime +14 -delete

# MinIO (older than 14 days)
find /backups/minio -type d -name "20[0-9][0-9]-[0-9][0-9]-[0-9][0-9]" -mtime +14 -exec rm -rf {} +
```

### Backup Storage

**Current storage location:**
- Docker volume: `backup_data`
- Mount point: `/backups` in backup container

**To backup to external storage:**
1. Mount external volume to `/backups` in backup container
2. Or use rsync/scp to copy backups to remote server
3. Or configure MinIO backup to S3-compatible storage

**Example external backup:**
```bash
# Copy to remote server
rsync -avz /backups/ user@backup-server:/backups/pdo/
```

## Testing

### Regular Testing Schedule

- **Weekly**: Run restore check on latest backup
- **Monthly**: Full restore test in staging environment
- **Quarterly**: Complete DR drill

### Test Restore Procedure

1. **Set up test environment:**
   ```bash
   docker-compose -f infra/docker-compose.yml up -d postgres
   ```

2. **Run restore check:**
   ```bash
   ./infra/scripts/restore_check.sh
   ```

3. **Verify results:**
   - All critical tables exist
   - Data counts are reasonable
   - No errors in restore process

## Troubleshooting

### Backup Fails

**Check logs:**
```bash
docker logs pdo_backup
```

**Common issues:**
- Database connection failure → Check postgres container
- MinIO connection failure → Check minio container
- Disk space full → Check `/backups` volume
- Permission issues → Check backup container permissions

### Restore Fails

**Check backup file:**
```bash
gunzip -t /backups/postgres/2024-01-15.sql.gz
```

**Check database:**
```bash
docker exec pdo_postgres pg_isready -U laravel
```

**Common issues:**
- Corrupted backup file → Use older backup
- Database not ready → Wait for PostgreSQL to start
- Permission errors → Check database user permissions

## Contact

For DR-related issues, contact:
- **DevOps Team**: [Contact Information]
- **On-Call Engineer**: [Contact Information]

## Appendix

### Backup Scripts Location

- `infra/scripts/backup-all.sh` - Main backup orchestrator
- `infra/scripts/backup-postgres.sh` - PostgreSQL backup
- `infra/scripts/backup-minio.sh` - MinIO backup
- `infra/scripts/restore_check.sh` - Backup verification

### Related Documentation

- [Architecture Documentation](./architecture.md)
- [Deployment Guide](../DEPLOYMENT.md)
- [MinIO Setup](./minio-setup.md)

