$ErrorActionPreference = "Stop"

# ============================================
# Studyback - Development Environment Starter
# Docker Compose
# ============================================

$RootDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ComposeFile = Join-Path $RootDir "docker-compose.yml"

# ============================================
# Header
# ============================================

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "      Studyback Development Environment     " -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# ============================================
# Validate Docker Compose file
# ============================================

if (-not (Test-Path $ComposeFile)) {
    Write-Host "[ERROR] docker-compose.yml not found:" -ForegroundColor Red
    Write-Host "        $ComposeFile" -ForegroundColor Red
    Write-Host ""
    exit 1
}

# ============================================
# Validate Docker
# ============================================

Write-Host "[1/3] Checking Docker..." -ForegroundColor Yellow

try {
    docker info *> $null

    if ($LASTEXITCODE -ne 0) {
        throw "Docker is not running."
    }
}
catch {
    Write-Host "[ERROR] Docker Desktop is not running." -ForegroundColor Red
    Write-Host "        Please start Docker Desktop first." -ForegroundColor Yellow
    Write-Host ""
    exit 1
}

Write-Host "      Docker is running." -ForegroundColor Green
Write-Host ""

# ============================================
# Start Docker Compose
# ============================================

Write-Host "[2/3] Starting Studyback containers..." -ForegroundColor Yellow
Write-Host ""

Set-Location $RootDir

docker compose up -d

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "[ERROR] Failed to start Studyback containers." -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host ""
Write-Host "      Containers started successfully." -ForegroundColor Green
Write-Host ""

# ============================================
# Show Container Status
# ============================================

Write-Host "[3/3] Checking container status..." -ForegroundColor Yellow
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
Write-Host "  Frontend : http://localhost:5173" -ForegroundColor Gray
Write-Host "  Backend  : http://localhost:8000" -ForegroundColor Gray
Write-Host "  Nginx    : http://localhost" -ForegroundColor Gray
Write-Host "  PostgreSQL: localhost:5432" -ForegroundColor Gray

Write-Host ""
Write-Host "Useful commands:" -ForegroundColor White
Write-Host "  Migration : .\migrate.ps1" -ForegroundColor Gray
Write-Host "  Stop      : .\stop-dev.ps1" -ForegroundColor Gray
Write-Host "  Status    : docker compose ps" -ForegroundColor Gray
Write-Host "  Logs      : docker compose logs -f" -ForegroundColor Gray

Write-Host ""