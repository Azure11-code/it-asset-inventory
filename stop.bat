@echo off
setlocal
title IT Asset Inventory - Stop

:: Stops the IT Asset Inventory containers. Database data is kept (docker volume).
cd /d "%~dp0"

echo Stopping IT Asset Inventory containers...
docker compose down
echo.
echo Done. Start again with start.bat
pause
