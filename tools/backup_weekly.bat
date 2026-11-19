@echo off
REM Weekly restore-point backup
REM Suggested schedule: every Sunday 2:00 AM

set PHP_BIN=C:\xampp\php\php.exe
set APP_BACKUP_SCRIPT=%~dp0backup.php

if exist "%PHP_BIN%" (
    "%PHP_BIN%" "%APP_BACKUP_SCRIPT%" --kind=weekly
) else (
    php "%APP_BACKUP_SCRIPT%" --kind=weekly
)

