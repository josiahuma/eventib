$ErrorActionPreference = "Stop"
$projectRoot = Split-Path -Parent $PSScriptRoot
Push-Location $projectRoot
try {
    if (!(Test-Path "artisan")) { throw "Run this inside your complete Laravel project." }
    Get-ChildItem app,routes -Recurse -Filter *.php | ForEach-Object {
        & php -l $_.FullName
        if ($LASTEXITCODE -ne 0) { throw "PHP syntax check failed: $($_.FullName)" }
    }
    & php -l tests/Feature/EventReleaseReviewTest.php
    if ($LASTEXITCODE -ne 0) { throw "PHP test syntax check failed." }
    & composer check-platform-reqs
    if ($LASTEXITCODE -ne 0) { throw "Composer platform requirements failed." }
    & php artisan optimize:clear
    if ($LASTEXITCODE -ne 0) { throw "Laravel boot/cache clear failed." }
    & php artisan route:cache
    if ($LASTEXITCODE -ne 0) { throw "Route cache failed." }
    & php artisan view:cache
    if ($LASTEXITCODE -ne 0) { throw "Blade compilation failed." }
    & php artisan route:clear
    if ($LASTEXITCODE -ne 0) { throw "Route cache cleanup failed." }
    & node tests/Frontend/eventib-booking-check.cjs
    if ($LASTEXITCODE -ne 0) { throw "Booking frontend checks failed." }
    & npm run build
    if ($LASTEXITCODE -ne 0) { throw "Frontend build failed." }
    Write-Host "Preflight passed. Run Laravel tests on a separate test database and complete the booking/Stripe smoke checks."
} finally { Pop-Location }
