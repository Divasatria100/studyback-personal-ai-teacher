$ErrorActionPreference = "Stop"

# ============================================
# Studyback - Development Environment Starter
# Docker Compose
# ============================================

$RootDir = $PSScriptRoot
$ComposeFile = Join-Path $RootDir "docker-compose.yml"

$RootEnv = Join-Path $RootDir ".env"
$RootEnvExample = Join-Path $RootDir ".env.example"

$BackendDir = Join-Path $RootDir "backend"
$BackendEnv = Join-Path $BackendDir ".env"
$BackendEnvExample = Join-Path $BackendDir ".env.example"

# ============================================
# Header
# ============================================

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "      Studyback Development Environment     " -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# ============================================
# Validate Docker Compose File
# ============================================

if (-not (Test-Path $ComposeFile)) {
    Write-Host "[ERROR] docker-compose.yml not found:" -ForegroundColor Red
    Write-Host "        $ComposeFile" -ForegroundColor Red
    exit 1
}

# ============================================
# Prepare Environment Files
# ============================================

Write-Host "[1/4] Checking environment files..." -ForegroundColor Yellow

# Root .env
if (-not (Test-Path $RootEnv)) {

    if (-not (Test-Path $RootEnvExample)) {
        Write-Host "[ERROR] Root .env.example not found:" -ForegroundColor Red
        Write-Host "        $RootEnvExample" -ForegroundColor Red
        exit 1
    }

    Copy-Item $RootEnvExample $RootEnv

    Write-Host "      Created root .env from .env.example." -ForegroundColor Green
}
else {
    Write-Host "      Root .env already exists. Keeping existing file." -ForegroundColor DarkGray
}

# Backend .env
if (-not (Test-Path $BackendEnv)) {

    if (-not (Test-Path $BackendEnvExample)) {
        Write-Host "[ERROR] Backend .env.example not found:" -ForegroundColor Red
        Write-Host "        $BackendEnvExample" -ForegroundColor Red
        exit 1
    }

    Copy-Item $BackendEnvExample $BackendEnv

    Write-Host "      Created backend .env from .env.example." -ForegroundColor Green
}
else {
    Write-Host "      Backend .env already exists. Keeping existing file." -ForegroundColor DarkGray
}

Write-Host ""

# ============================================
# Validate Docker
# ============================================

Write-Host "Checking Docker..." -ForegroundColor Yellow

try {
    docker info *> $null

    if ($LASTEXITCODE -ne 0) {
        throw "Docker is not running."
    }
}
catch {
    Write-Host "[ERROR] Docker Desktop is not running." -ForegroundColor Red
    Write-Host "        Please start Docker Desktop first." -ForegroundColor Yellow
    exit 1
}

Write-Host "      Docker is running." -ForegroundColor Green
Write-Host ""

# ============================================
# Start Docker Compose
# ============================================

Write-Host "[2/4] Starting Studyback containers..." -ForegroundColor Yellow
Write-Host ""

Set-Location $RootDir

docker compose up -d

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "[ERROR] Failed to start Studyback containers." -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host ""
Write-Host "      Docker Compose started." -ForegroundColor Green
Write-Host ""

# ============================================
# Verify Backend Container
# ============================================

Write-Host "[3/4] Checking backend container..." -ForegroundColor Yellow

$BackendStatus = docker compose ps --status running --services | Select-String "^backend$"

if (-not $BackendStatus) {

    Write-Host ""
    Write-Host "[ERROR] Backend container is not running." -ForegroundColor Red
    Write-Host ""
    Write-Host "Backend logs:" -ForegroundColor Yellow
    Write-Host "--------------------------------------------" -ForegroundColor DarkGray

    docker compose logs --tail=50 backend

    Write-Host "--------------------------------------------" -ForegroundColor DarkGray
    Write-Host ""

    exit 1
}

Write-Host "      Backend container is running." -ForegroundColor Green
Write-Host ""

# ============================================
# Generate Laravel APP_KEY
# ============================================

Write-Host "Checking Laravel APP_KEY..." -ForegroundColor Yellow

$BackendEnvContent = Get-Content $BackendEnv -Raw

if ($BackendEnvContent -match "(?m)^APP_KEY=\s*$") {

    Write-Host "      APP_KEY is empty. Generating..." -ForegroundColor Yellow

    docker compose exec -T backend php artisan key:generate

    if ($LASTEXITCODE -ne 0) {
        Write-Host ""
        Write-Host "[ERROR] Failed to generate Laravel APP_KEY." -ForegroundColor Red
        exit $LASTEXITCODE
    }

    Write-Host "      APP_KEY generated successfully." -ForegroundColor Green
}
else {
    Write-Host "      APP_KEY already exists. Keeping existing key." -ForegroundColor DarkGray
}

Write-Host ""

# ============================================
# Container Status
# ============================================

Write-Host "[4/4] Checking container status..." -ForegroundColor Yellow
Write-Host ""

docker compose ps

Write-Host ""

# ============================================
# Summary
# ============================================

Write-Host "============================================" -ForegroundColor Cyan
Write-Host " Development environment started!" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "Services:" -ForegroundColor White
Write-Host "  Frontend  : http://localhost:5173" -ForegroundColor Gray
Write-Host "  Backend   : http://localhost:8000" -ForegroundColor Gray
Write-Host "  Nginx     : http://localhost" -ForegroundColor Gray
Write-Host "  PostgreSQL: localhost:5432" -ForegroundColor Gray

Write-Host ""
Write-Host "Useful commands:" -ForegroundColor White
Write-Host "  Migration : .\migrate.ps1" -ForegroundColor Gray
Write-Host "  Stop      : .\stop-dev.ps1" -ForegroundColor Gray
Write-Host "  Status    : docker compose ps" -ForegroundColor Gray
Write-Host "  Logs      : docker compose logs -f" -ForegroundColor Gray

Write-Host ""