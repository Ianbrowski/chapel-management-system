@echo off
REM Run daily via Windows Task Scheduler
REM Example Program/script: C:\xampp\php\php.exe
REM Example arguments: C:\xampp\htdocs\sukaniggadih\newtypeshichap2\tools\backup.php

set PHP_BIN=C:\xampp\php\php.exe
set APP_BACKUP_SCRIPT=%~dp0backup.php

if exist "%PHP_BIN%" (
    "%PHP_BIN%" "%APP_BACKUP_SCRIPT%"
) else (
    php "%APP_BACKUP_SCRIPT%"
)

