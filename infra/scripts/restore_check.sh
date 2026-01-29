#!/bin/bash
set -euo pipefail

# Backup restore verification script
# Usage: restore_check.sh [backup_date] [backup_file]
# Example: restore_check.sh 2024-01-15 /backups/postgres/2024-01-15.sql.gz

BACKUP_DATE="${1:-$(date +%Y-%m-%d)}"
BACKUP_FILE="${2:-/backups/postgres/${BACKUP_DATE}.sql.gz}"

if [ ! -f "${BACKUP_FILE}" ]; then
    echo "ERROR: Backup file not found: ${BACKUP_FILE}" >&2
    exit 1
fi

echo "=========================================="
echo "Backup Restore Verification"
echo "=========================================="
echo "Backup file: ${BACKUP_FILE}"
echo "Date: ${BACKUP_DATE}"
echo ""

# Test database parameters
TEST_DB_NAME="pdo_backup_test_${BACKUP_DATE//-/}"
TEST_DB_USER="${DB_USERNAME:-laravel}"
TEST_DB_PASSWORD="${DB_PASSWORD:-password}"
TEST_DB_HOST="${DB_HOST:-localhost}"
# Use a random port to avoid conflicts
TEST_DB_PORT="${TEST_DB_PORT:-5433}"

# Export password for psql
export PGPASSWORD="${TEST_DB_PASSWORD}"

# Create test container name
TEST_CONTAINER="pdo_postgres_test_${BACKUP_DATE//-/}"

echo "--- Step 1: Starting test PostgreSQL container ---"
docker run -d \
    --name "${TEST_CONTAINER}" \
    -e POSTGRES_DB="${TEST_DB_NAME}" \
    -e POSTGRES_USER="${TEST_DB_USER}" \
    -e POSTGRES_PASSWORD="${TEST_DB_PASSWORD}" \
    -p "${TEST_DB_PORT}:5432" \
    postgres:16-alpine > /dev/null

# Wait for PostgreSQL to be ready
echo "Waiting for PostgreSQL to be ready..."
for i in {1..30}; do
    if docker exec "${TEST_CONTAINER}" pg_isready -U "${TEST_DB_USER}" > /dev/null 2>&1; then
        break
    fi
    sleep 1
done

if ! docker exec "${TEST_CONTAINER}" pg_isready -U "${TEST_DB_USER}" > /dev/null 2>&1; then
    echo "ERROR: PostgreSQL container failed to start" >&2
    docker rm -f "${TEST_CONTAINER}" > /dev/null 2>&1 || true
    exit 1
fi

echo "PostgreSQL container is ready"

echo ""
echo "--- Step 2: Restoring backup ---"
gunzip -c "${BACKUP_FILE}" | docker exec -i "${TEST_CONTAINER}" psql -U "${TEST_DB_USER}" -d "${TEST_DB_NAME}" 2>&1 | grep -v "NOTICE:" | grep -v "WARNING:" || {
    echo "ERROR: Restore failed" >&2
    docker rm -f "${TEST_CONTAINER}" > /dev/null 2>&1 || true
    exit 1
}

echo "Backup restored successfully"

echo ""
echo "--- Step 3: Verifying tables and data ---"

# Check for tenants table
TENANTS_COUNT=$(docker exec "${TEST_CONTAINER}" psql -U "${TEST_DB_USER}" -d "${TEST_DB_NAME}" -t -c "SELECT COUNT(*) FROM tenants;" 2>/dev/null | tr -d ' ' || echo "0")
if [ "${TENANTS_COUNT}" = "0" ] || [ -z "${TENANTS_COUNT}" ]; then
    echo "WARNING: tenants table is empty or missing"
else
    echo "✓ tenants table exists with ${TENANTS_COUNT} record(s)"
fi

# Check for users table
USERS_COUNT=$(docker exec "${TEST_CONTAINER}" psql -U "${TEST_DB_USER}" -d "${TEST_DB_NAME}" -t -c "SELECT COUNT(*) FROM users;" 2>/dev/null | tr -d ' ' || echo "0")
if [ "${USERS_COUNT}" = "0" ] || [ -z "${USERS_COUNT}" ]; then
    echo "WARNING: users table is empty or missing"
else
    echo "✓ users table exists with ${USERS_COUNT} record(s)"
fi

# Check for other important tables
IMPORTANT_TABLES=("groups" "subjects" "rooms" "schedule_items" "assignments" "documents")
for table in "${IMPORTANT_TABLES[@]}"; do
    TABLE_EXISTS=$(docker exec "${TEST_CONTAINER}" psql -U "${TEST_DB_USER}" -d "${TEST_DB_NAME}" -t -c "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = '${table}');" 2>/dev/null | tr -d ' ' || echo "f")
    if [ "${TABLE_EXISTS}" = "t" ]; then
        echo "✓ ${table} table exists"
    else
        echo "⚠ ${table} table missing"
    fi
done

echo ""
echo "--- Step 4: Cleaning up test container ---"
docker rm -f "${TEST_CONTAINER}" > /dev/null 2>&1 || true

echo ""
echo "=========================================="
echo "Restore verification completed"
echo "=========================================="

if [ "${TENANTS_COUNT}" = "0" ] || [ "${USERS_COUNT}" = "0" ]; then
    echo ""
    echo "WARNING: Critical tables are empty. Backup may be incomplete."
    exit 1
fi

echo ""
echo "✓ Backup verification passed"

