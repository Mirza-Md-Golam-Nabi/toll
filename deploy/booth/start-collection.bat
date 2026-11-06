@echo off
REM ============================================================
REM  Opens the toll collection screen full-screen.
REM  --kiosk-printing prints receipts silently on the DEFAULT printer,
REM  so set the 58mm thermal printer as the Windows default printer.
REM  Exit kiosk mode with Alt+F4.
REM ============================================================
set "URL=http://toll-management.test/admin/collection"
set "CHROME=C:\Program Files\Google\Chrome\Application\chrome.exe"
if not exist "%CHROME%" set "CHROME=C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"

if exist "%CHROME%" (
    start "" "%CHROME%" --kiosk --kiosk-printing --app=%URL%
) else (
    start "" msedge --kiosk %URL% --edge-kiosk-type=fullscreen --kiosk-printing
)
