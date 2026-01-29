# Scripts

## Smoke Tests

Smoke tests verify that the application can start up correctly with all dependencies.

### Linux/macOS

```bash
./scripts/smoke-test.sh
```

### Windows PowerShell

```powershell
.\scripts\smoke-test.ps1
```

### What it does

1. Starts Docker Compose services (PostgreSQL, Redis)
2. Waits for services to be ready
3. Runs database migrations
4. Runs seeders (TenantSeeder)
5. Starts Laravel server
6. Tests `/api/health` endpoint
7. Cleans up and stops services

### Requirements

- Docker and Docker Compose
- PHP 8.2+ with required extensions
- Composer dependencies installed in `backend/`
- `.env` file configured in `backend/`

### Environment Variables

The script uses environment variables from `backend/.env`. Make sure these are set:

- `DB_CONNECTION=pgsql`
- `DB_HOST=localhost` (or `postgres` in Docker)
- `DB_PORT=5432`
- `DB_DATABASE=laravel_test`
- `DB_USERNAME=laravel`
- `DB_PASSWORD=password`
- `REDIS_HOST=localhost` (or `redis` in Docker)
- `REDIS_PORT=6379`
- `APP_KEY` (generated)
- `JWT_SECRET`
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`

