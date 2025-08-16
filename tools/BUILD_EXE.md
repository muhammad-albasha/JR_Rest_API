This repo contains PHP scripts. To distribute as a Windows .exe without requiring a global PHP installation, use a portable PHP runtime bundled with the app. Below are two options.

Option A: Minimal self-contained EXE using 7-Zip SFX
- Result: A single .exe that extracts to a temp folder at runtime, runs portable php.exe on watchInvoices.php, and cleans up on exit.
- Pros: Single file, no installer. Cons: Basic and AV false positives are possible.

Steps
1) Download portable PHP 8.2+ for Windows (x64 Non Thread Safe works) from https://windows.php.net/downloads/releases/ and unzip to tools\php\
   Required extensions: curl, openssl, zip
   - Enable them in tools\php\php.ini by ensuring these lines exist:
       extension_dir = "ext"
       extension=curl
       extension=openssl
       extension=zip

2) Create runtime wrapper script at tools\run.bat with content:
   @echo off
   setlocal
   set APP_ROOT=%~dp0..\
   set PHP_DIR=%~dp0php\
   set PHP_EXE=%PHP_DIR%php.exe
   if not exist "%PHP_EXE%" (
     echo Portable PHP not found at %PHP_EXE%
     pause
     exit /b 1
   )
   cd /d "%APP_ROOT%\Rest_API\Service"
   "%PHP_EXE%" -d detect_unicode=0 -f watchInvoices.php

3) Prepare a staging folder structure (outside the repo or under tools\dist\payload):
   payload\
     php\            <- portable php runtime with php.ini and extensions
     app\            <- the project contents you need at runtime
       Rest_API\
       Bearbeitete_Rechnung\ (optional)
     run.bat

4) Build a self-extracting EXE with 7-Zip SFX
   - Install 7-Zip and get the SFX module (7z.sfx).
   - Create payload.7z from the payload folder.
   - Create a config.txt with:
       ;!@Install@!UTF-8!
       Title="JR_Rest_API"
       RunProgram="run.bat"
       GUIMode="1"
       ;!@InstallEnd@!
   - Concatenate to make exe:
       copy /b 7z.sfx + config.txt + payload.7z JR_Rest_API.exe

Option B: Use PHP Desktop or Caddy + php-cgi
- Bundle a small HTTP server and php-cgi, then run your existing endpoints. Heavier, but useful if you want a local web server UI.

Notes
- The script watchInvoices.php is a long-running console watcher. The EXE will display a console window showing logs.
- Ensure Rest_API/config.php contains correct base URL and credentials for the target environment.
- Add a README note for users to run as Administrator if watching a protected folder.



Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass -Force; .\tools\build_exe.ps1

Get-ChildItem -Recurse -Depth 1 "c:\Users\Muhammad\Desktop\JR_Rest_API\tools\dist\payload\php" | Select-Object FullName

Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass -Force
.\tools\build_exe.ps1 -SevenZipDir "C:\Program Files\7-Zip"