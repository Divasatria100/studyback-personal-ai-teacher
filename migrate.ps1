# migrate.ps1
$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host " Studyback - Laravel Migration" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "[1/2] Checking backend container..." -ForegroundColor Yellow

$backendRunning = docker compose ps --status running --services | Select-String "^backend$"

if (-not $backendRunning) {
    Write-Host "Backend container is not running." -ForegroundColor Red
    Write-Host ""
    Write-Host "Start Studyback first with:" -ForegroundColor Yellow
    Write-Host "  .\start-dev.ps1" -ForegroundColor White
    exit 1
}

Write-Host "Backend container is running." -ForegroundColor Green
Write-Host ""

Write-Host "[2/2] Running Laravel migrations..." -ForegroundColor Yellow

docker compose exec backend php artisan migrate

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "Migration completed successfully." -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "Migration failed." -ForegroundColor Red
    exit $LASTEXITCODE
}