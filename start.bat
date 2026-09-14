@echo off
setlocal
title IT Asset Inventory - Start

:: Starts the IT Asset Inventory stack (nginx, php app, mysql, vite) in Docker.
:: Double-click this file, or run from a terminal:  start.bat
:: Stop everything with:  docker compose down

cd /d "%~dp0"

echo ============================================
echo   IT Asset Inventory - starting in Docker
echo ============================================
echo.

:: ---- 1. Make sure Docker Desktop is running ----------------------------
docker info >nul 2>&1
if %errorlevel%==0 goto docker_ready

echo Docker Desktop is not running. Launching it...
set "DOCKER_EXE=%ProgramFiles%\Docker\Docker\Docker Desktop.exe"
if not exist "%DOCKER_EXE%" set "DOCKER_EXE=%LOCALAPPDATA%\Docker\Docker Desktop.exe"
if not exist "%DOCKER_EXE%" (
    echo ERROR: Docker Desktop.exe not found. Please start Docker Desktop manually.
    goto fail
)
start "" "%DOCKER_EXE%"

set /a tries=0
:wait_docker
ping -n 5 127.0.0.1 >nul
docker info >nul 2>&1
if %errorlevel%==0 goto docker_ready
set /a tries+=1
if %tries% geq 45 (
    echo ERROR: Docker did not become ready after 3 minutes.
    goto fail
)
echo   waiting for Docker engine... (%tries%)
goto wait_docker

:docker_ready
echo Docker engine is ready.
echo.

:: ---- 2. Make sure .env exists ------------------------------------------
if not exist ".env" (
    echo No .env found - copying .env.example
    copy /y ".env.example" ".env" >nul
)

:: ---- 3. Start the containers --------------------------------------------
echo Starting containers (first run builds the image - may take a few minutes)...
docker compose up -d --build
if %errorlevel% neq 0 (
    echo ERROR: docker compose up failed. See output above.
    goto fail
)

:: ---- 4. Wait for the app to answer --------------------------------------
echo.
echo Waiting for the app to respond...
set /a tries=0
:wait_app
ping -n 4 127.0.0.1 >nul
curl -s -o nul -w "%%{http_code}" http://localhost:8081/up 2>nul | findstr /c:"200" >nul
if %errorlevel%==0 goto app_ready
set /a tries+=1
if %tries% geq 40 (
    echo WARNING: app not responding yet. Check logs with:  docker compose logs -f app
    goto show_status
)
goto wait_app

:app_ready
echo App is up.

:show_status
echo.
docker compose ps
echo.

:: ---- 5. Show URLs ---------------------------------------------------------
for /f "tokens=2 delims==" %%u in ('findstr /b /c:"APP_URL=" .env') do set "APP_URL=%%u"
echo ============================================
echo   App URL   : %APP_URL%
echo   Local     : http://localhost:8081
echo   HTTPS/scan: https://localhost:8443
echo   Vite HMR  : http://localhost:5174
echo   MySQL     : localhost:3308
echo.
echo   Stop with : docker compose down
echo   Logs with : docker compose logs -f
echo ============================================
echo.
pause
exit /b 0

:fail
echo.
pause
exit /b 1
