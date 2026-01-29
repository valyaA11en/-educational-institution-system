#!/bin/bash

set -euo pipefail

echo "=== Smoke Tests ==="

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if docker-compose is available
if ! command -v docker-compose &> /dev/null && ! command -v docker &> /dev/null; then
    echo -e "${RED}Error: docker-compose or docker is required${NC}"
    exit 1
fi

COMPOSE_CMD="docker-compose"
if ! command -v docker-compose &> /dev/null; then
    COMPOSE_CMD="docker compose"
fi

# Start services
echo -e "${YELLOW}Starting Docker Compose services...${NC}"
$COMPOSE_CMD up -d postgres redis

# Wait for services to be ready
echo -e "${YELLOW}Waiting for services to be ready...${NC}"
sleep 5

# Check PostgreSQL
echo -e "${YELLOW}Checking PostgreSQL...${NC}"
for i in {1..30}; do
    if $COMPOSE_CMD exec -T postgres pg_isready -U laravel > /dev/null 2>&1; then
        echo -e "${GREEN}PostgreSQL is ready${NC}"
        break
    fi
    if [ $i -eq 30 ]; then
        echo -e "${RED}PostgreSQL failed to start${NC}"
        exit 1
    fi
    sleep 1
done

# Check Redis
echo -e "${YELLOW}Checking Redis...${NC}"
for i in {1..30}; do
    if $COMPOSE_CMD exec -T redis redis-cli ping > /dev/null 2>&1; then
        echo -e "${GREEN}Redis is ready${NC}"
        break
    fi
    if [ $i -eq 30 ]; then
        echo -e "${RED}Redis failed to start${NC}"
        exit 1
    fi
    sleep 1
done

# Run migrations
echo -e "${YELLOW}Running migrations...${NC}"
cd backend
php artisan migrate --force || {
    echo -e "${RED}Migrations failed${NC}"
    exit 1
}
echo -e "${GREEN}Migrations completed${NC}"

# Run seeders
echo -e "${YELLOW}Running seeders...${NC}"
php artisan db:seed --class=TenantSeeder || {
    echo -e "${YELLOW}Seeder skipped or failed (non-critical)${NC}"
}
echo -e "${GREEN}Seeders completed${NC}"

# Start Laravel server in background
echo -e "${YELLOW}Starting Laravel server...${NC}"
php artisan serve --host=0.0.0.0 --port=8000 > /tmp/laravel-server.log 2>&1 &
SERVER_PID=$!

# Wait for server to start
sleep 5

# Check if server is running
if ! kill -0 $SERVER_PID 2>/dev/null; then
    echo -e "${RED}Laravel server failed to start${NC}"
    cat /tmp/laravel-server.log
    exit 1
fi

# Test health endpoint
echo -e "${YELLOW}Testing /api/health endpoint...${NC}"
for i in {1..10}; do
    if curl -f -s http://localhost:8000/api/health > /dev/null; then
        echo -e "${GREEN}Health check passed${NC}"
        HEALTH_CHECK_PASSED=true
        break
    fi
    if [ $i -eq 10 ]; then
        echo -e "${RED}Health check failed after 10 attempts${NC}"
        HEALTH_CHECK_PASSED=false
    fi
    sleep 2
done

# Cleanup
echo -e "${YELLOW}Stopping Laravel server...${NC}"
kill $SERVER_PID 2>/dev/null || true
wait $SERVER_PID 2>/dev/null || true

# Stop Docker services
echo -e "${YELLOW}Stopping Docker services...${NC}"
cd ..
$COMPOSE_CMD down

# Final result
if [ "${HEALTH_CHECK_PASSED:-false}" = "true" ]; then
    echo -e "${GREEN}=== Smoke Tests PASSED ===${NC}"
    exit 0
else
    echo -e "${RED}=== Smoke Tests FAILED ===${NC}"
    exit 1
fi

