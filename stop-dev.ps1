$ErrorActionPreference = "Stop"

# ============================================
# Studyback - Development Environment Stopper
# Docker Compose
# ============================================

$RootDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ComposeFile = Join-Path $RootDir "docker-compose.yml"

# ============================================
# Header
# ============================================

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "       Stopping Studyback Development       " -ForegroundColor Cyan
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

Write-Host "[1/2] Checking Docker..." -ForegroundColor Yellow

try {
    docker info *> $null

    if ($LASTEXITCODE -ne 0) {
        throw "Docker is not running."
    }
}
catch {
    Write-Host "[ERROR] Docker Desktop is not running." -ForegroundColor Red
    Write-Host ""
    exit 1
}

Write-Host "      Docker is running." -ForegroundColor Green
Write-Host ""

# ============================================
# Stop Docker Compose
# ============================================

Write-Host "[2/2] Stopping Studyback containers..." -ForegroundColor Yellow
Write-Host ""

Set-Location $RootDir

docker compose stop

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "[ERROR] Failed to stop Studyback containers." -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host ""
Write-Host "      Containers stopped successfully." -ForegroundColor Green
Write-Host ""

# ============================================
# Show Container Status
# ============================================

Write-Host "Container status:" -ForegroundColor White
Write-Host ""

docker compose ps

Write-Host ""

# ============================================
# Summary
# ============================================

Write-Host "============================================" -ForegroundColor Cyan
Write-Host " Development environment stopped!" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "Containers have been stopped but preserved." -ForegroundColor Gray
Write-Host "Docker volumes and database data are preserved." -ForegroundColor Gray

Write-Host ""
Write-Host "To start again:" -ForegroundColor White
Write-Host "  .\start-dev.ps1" -ForegroundColor Gray

Write-Host ""