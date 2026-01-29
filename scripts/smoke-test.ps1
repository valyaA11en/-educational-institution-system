# Smoke Tests for Windows PowerShell

$ErrorActionPreference = "Stop"

Write-Host "=== Smoke Tests ===" -ForegroundColor Cyan

# Check if docker-compose is available
$composeCmd = "docker-compose"
if (-not (Get-Command docker-compose -ErrorAction SilentlyContinue)) {
    if (Get-Command docker -ErrorAction SilentlyContinue) {
        $composeCmd = "docker compose"
    } else {
        Write-Host "Error: docker-compose or docker is required" -ForegroundColor Red
        exit 1
    }
}

# Start services
Write-Host "Starting Docker Compose services..." -ForegroundColor Yellow
& $composeCmd.Split(' ') up -d postgres redis

# Wait for services to be ready
Write-Host "Waiting for services to be ready..." -ForegroundColor Yellow
Start-Sleep -Seconds 5

# Check PostgreSQL
Write-Host "Checking PostgreSQL..." -ForegroundColor Yellow
$postgresReady = $false
for ($i = 1; $i -le 30; $i++) {
    $result = & $composeCmd.Split(' ') exec -T postgres pg_isready -U laravel 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "PostgreSQL is ready" -ForegroundColor Green
        $postgresReady = $true
        break
    }
    Start-Sleep -Seconds 1
}
if (-not $postgresReady) {
    Write-Host "PostgreSQL failed to start" -ForegroundColor Red
    exit 1
}

# Check Redis
Write-Host "Checking Redis..." -ForegroundColor Yellow
$redisReady = $false
for ($i = 1; $i -le 30; $i++) {
    $result = & $composeCmd.Split(' ') exec -T redis redis-cli ping 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Redis is ready" -ForegroundColor Green
        $redisReady = $true
        break
    }
    Start-Sleep -Seconds 1
}
if (-not $redisReady) {
    Write-Host "Redis failed to start" -ForegroundColor Red
    exit 1
}

# Run migrations
Write-Host "Running migrations..." -ForegroundColor Yellow
Push-Location backend
php artisan migrate --force
if ($LASTEXITCODE -ne 0) {
    Write-Host "Migrations failed" -ForegroundColor Red
    Pop-Location
    exit 1
}
Write-Host "Migrations completed" -ForegroundColor Green

# Run seeders
Write-Host "Running seeders..." -ForegroundColor Yellow
php artisan db:seed --class=TenantSeeder
if ($LASTEXITCODE -ne 0) {
    Write-Host "Seeder skipped or failed (non-critical)" -ForegroundColor Yellow
}
Write-Host "Seeders completed" -ForegroundColor Green

# Start Laravel server in background
Write-Host "Starting Laravel server..." -ForegroundColor Yellow
$serverJob = Start-Job -ScriptBlock {
    Set-Location $using:PWD
    php artisan serve --host=0.0.0.0 --port=8000
}

# Wait for server to start
Start-Sleep -Seconds 5

# Test health endpoint
Write-Host "Testing /api/health endpoint..." -ForegroundColor Yellow
$healthCheckPassed = $false
for ($i = 1; $i -le 10; $i++) {
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:8000/api/health" -UseBasicParsing -TimeoutSec 5
        if ($response.StatusCode -eq 200) {
            Write-Host "Health check passed" -ForegroundColor Green
            $healthCheckPassed = $true
            break
        }
    } catch {
        if ($i -eq 10) {
            Write-Host "Health check failed after 10 attempts" -ForegroundColor Red
        }
    }
    Start-Sleep -Seconds 2
}

# Cleanup
Write-Host "Stopping Laravel server..." -ForegroundColor Yellow
Stop-Job $serverJob -ErrorAction SilentlyContinue
Remove-Job $serverJob -ErrorAction SilentlyContinue

# Stop Docker services
Write-Host "Stopping Docker services..." -ForegroundColor Yellow
Pop-Location
& $composeCmd.Split(' ') down

# Final result
if ($healthCheckPassed) {
    Write-Host "=== Smoke Tests PASSED ===" -ForegroundColor Green
    exit 0
} else {
    Write-Host "=== Smoke Tests FAILED ===" -ForegroundColor Red
    exit 1
}

