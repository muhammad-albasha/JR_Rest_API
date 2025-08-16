@echo off
setlocal
REM Wrapper to run the watcher using a local portable PHP runtime or system PHP
set APP_ROOT=%~dp0..\
set PHP_DIR=%~dp0php\
set PHP_EXE=%PHP_DIR%php.exe

if not exist "%PHP_EXE%" (
  echo Portable PHP not found at %PHP_EXE%
  for /f "usebackq delims=" %%P in (`where php.exe 2^>nul`) do (
    set "PHP_EXE=%%P"
    goto :FoundPhp
  )
  echo No portable PHP and no system php.exe found in PATH.
  echo Download a zip from https://windows.php.net/downloads/releases/ and extract into tools\php\
  pause
  exit /b 1
)

:FoundPhp
echo JobArchive service is running
cd /d "%APP_ROOT%\Rest_API\Service"
"%PHP_EXE%" -f watchInvoices.php
