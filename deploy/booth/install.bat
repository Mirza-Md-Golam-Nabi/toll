@echo off
REM ============================================================
REM  Toll Management - booth PC installation (run from a Laragon terminal)
REM  Prerequisite: project copied to C:\laragon\www\toll-management
REM               and .env created from deploy\booth\.env.booth.example
REM ============================================================
setlocal
cd /d "%~dp0..\.."

if not exist ".env" (
    echo [ERROR] .env not found. Copy deploy\booth\.env.booth.example to .env first.
    exit /b 1
)

echo [1/7] Installing PHP dependencies...
call composer install --no-dev --optimize-autoloader --no-interaction || exit /b 1

echo [2/7] Generating application key (only if empty)...
findstr /r /c:"^APP_KEY=base64" .env >nul || php artisan key:generate --force

echo [3/7] Creating database tables...
php artisan migrate --force || exit /b 1

echo [4/7] Loading master data...
REM Stand-alone booth: seeds booth, shifts and categories.
REM Connected booth: the seeder skips and toll:sync downloads everything from central.
php artisan db:seed --force || exit /b 1
php artisan toll:sync

echo [5/7] Publishing panel assets...
php artisan filament:assets || exit /b 1

echo [6/7] Caching config, routes and views...
php artisan optimize || exit /b 1

echo [7/7] First Super Admin account
echo        Connected booth: users come from central - press Ctrl+C to skip.
php artisan toll:create-admin

echo.
echo Done. Next: run deploy\booth\register-scheduler.bat as Administrator
echo       and put deploy\booth\start-collection.bat on the desktop.
endlocal
