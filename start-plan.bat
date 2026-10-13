@echo off
setlocal

set "PLAN_ROOT=%~dp0"
set "PLAN_PHP=C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe"

if not exist "%PLAN_PHP%" (
    echo [IPRPMS] Required PHP executable was not found: %PLAN_PHP%
    exit /b 1
)

for /f "tokens=5" %%P in ('netstat -ano ^| findstr /R /C:"127.0.0.1:8000 .*LISTENING"') do (
    echo [IPRPMS] Stopping the existing process on port 8000 ^(PID %%P^)...
    taskkill /PID %%P /F >nul 2>&1
)

echo [IPRPMS] PHP runtime:
"%PLAN_PHP%" -r "echo PHP_VERSION, PHP_EOL;"
echo [IPRPMS] Starting the optimized server at http://127.0.0.1:8000

cd /d "%PLAN_ROOT%"
"%PLAN_PHP%" -S 127.0.0.1:8000 -t "%PLAN_ROOT%public" "%PLAN_ROOT%public\dev-server.php"

