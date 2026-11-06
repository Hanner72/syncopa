@echo off
FOR /F "tokens=*" %%i IN ('git rev-parse --show-toplevel') DO SET REPO_ROOT=%%i
php "%REPO_ROOT%\version_sync.php"
IF %ERRORLEVEL% NEQ 0 EXIT /B 1
