@echo off
REM ============================================================
REM  Registers a Windows scheduled task that runs the Laravel scheduler
REM  every minute (offline -> central auto-sync). Run as Administrator.
REM ============================================================
setlocal
set "APP_DIR=%~dp0..\.."
for %%I in ("%APP_DIR%") do set "APP_DIR=%%~fI"

for /f "delims=" %%P in ('where php') do (
    set "PHP_EXE=%%P"
    goto :found
)
echo [ERROR] php.exe not found in PATH. Open a Laragon terminal and try again.
exit /b 1

:found
schtasks /Create /F /SC MINUTE /MO 1 /TN "TollManagementScheduler" ^
    /TR "\"%PHP_EXE%\" \"%APP_DIR%\artisan\" schedule:run" ^
    /RU SYSTEM /RL HIGHEST || exit /b 1

echo Scheduled task "TollManagementScheduler" registered (every minute).
echo Check it any time with:  php artisan toll:sync
endlocal
