[CmdletBinding()]
param(
  [string]$OutName = "JR_Rest_API.exe",
  [string]$SevenZipDir = "",
  [switch]$SkipPhp,
  [string]$PhpZipPath = "",
  [string]$PhpIniPath = ""
)

$ErrorActionPreference = 'Stop'

function Write-Info($msg) { Write-Host "[INFO] $msg" -ForegroundColor Cyan }
function Write-Warn($msg) { Write-Host "[WARN] $msg" -ForegroundColor Yellow }
function Write-Err($msg)  { Write-Host "[ERR ] $msg" -ForegroundColor Red }

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RepoRoot  = Resolve-Path (Join-Path $ScriptDir "..")
$DistDir   = Join-Path $ScriptDir "dist"
$Payload   = Join-Path $DistDir "payload"
$AppDir    = Join-Path $Payload "app"
$PhpDir    = Join-Path $Payload "php"
$RunBat    = Join-Path $Payload "run.bat"
$CfgPath   = Join-Path $DistDir  "config.txt"
$SevenZ    = Join-Path $DistDir  "payload.7z"
$OutExe    = Join-Path $DistDir  $OutName

Write-Info "Repo:    $RepoRoot"
Write-Info "Dist:    $DistDir"
Write-Info "Payload: $Payload"

# Clean and re-create dist
if (Test-Path $DistDir) { Remove-Item -Recurse -Force $DistDir }
New-Item -ItemType Directory -Force -Path $Payload | Out-Null
New-Item -ItemType Directory -Force -Path $AppDir | Out-Null
New-Item -ItemType Directory -Force -Path $PhpDir | Out-Null

# Copy app content
$toCopy = @()
$restApi = Join-Path $RepoRoot "Rest_API"
if (Test-Path $restApi) { $toCopy += $restApi } else { Write-Err "Missing '$restApi'" }
$bearb = Join-Path $RepoRoot "Bearbeitete_Rechnung"
if (Test-Path $bearb) { $toCopy += $bearb }

foreach ($p in $toCopy) {
  Write-Info "Copying $(Split-Path -Leaf $p) -> $AppDir"
  Copy-Item -Recurse -Force -Path $p -Destination $AppDir
}

# Prepare PHP runtime in payload
if (-not $SkipPhp) {
  if ($PhpZipPath -and (Test-Path $PhpZipPath)) {
    Write-Info "Expanding PHP zip -> $PhpDir"
    Expand-Archive -Path $PhpZipPath -DestinationPath $PhpDir
    # If expanded into a nested folder (php-8.x.x-...), flatten if needed
    $inner = Get-ChildItem -Path $PhpDir -Directory -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($inner) {
      Write-Info "Flattening PHP folder structure"
      Copy-Item -Recurse -Force (Join-Path $inner.FullName '*') $PhpDir
      Remove-Item -Recurse -Force $inner.FullName
    }
  } else {
    # Try copying from tools\php as a convenience
    $toolsPhp = Join-Path $ScriptDir "php"
    if (Test-Path $toolsPhp) {
      Write-Info "Copying portable PHP from tools\\php -> $PhpDir"
      # Copy content (avoid creating payload\php\php)
      Copy-Item -Recurse -Force -Path (Join-Path $toolsPhp '*') -Destination $PhpDir
    } else {
      Write-Warn "No PHP provided. The EXE will require you to place a portable PHP inside payload\\php before running."
    }
  }

  if ($PhpIniPath -and (Test-Path $PhpIniPath)) {
    Write-Info "Copying php.ini"
    Copy-Item -Force $PhpIniPath (Join-Path $PhpDir "php.ini")
  }

  # Final sanity: flatten nested php directory if present (payload\php\php\...)
  $nestedPhp = Join-Path $PhpDir 'php'
  if (Test-Path $nestedPhp) {
    try {
      Write-Info "Flattening nested php folder"
      Copy-Item -Recurse -Force -Path (Join-Path $nestedPhp '*') -Destination $PhpDir
      Remove-Item -Recurse -Force $nestedPhp
    } catch {
      Write-Warn "Could not flatten nested php folder: $($_.Exception.Message)"
    }
  }
}

# Create run.bat inside payload
Write-Info "Creating run.bat"
$runBatContent = @"
@echo off
setlocal
set SFX_DIR=%~dp0
set APP_ROOT=%SFX_DIR%app\
set PHP_DIR=%SFX_DIR%php\
set PHP_EXE=%PHP_DIR%php.exe
if not exist "%PHP_EXE%" (
  echo Portable PHP not found at %PHP_EXE%
  for /f "usebackq delims=" %%P in (`where php.exe 2^>nul`) do (
    set "PHP_EXE=%%P"
    goto :FoundPhp
  )
  echo No portable PHP and no system php.exe found in PATH.
  echo Place a portable PHP runtime into the 'php' folder.
  pause
  exit /b 1
)
:FoundPhp
echo JobArchive service is running
cd /d "%APP_ROOT%\Rest_API\Service"
"%PHP_EXE%" -f watchInvoices.php
pause
"@
Set-Content -Path $RunBat -Value $runBatContent -Encoding ASCII

# Create SFX config
Write-Info "Creating SFX config"
$cfgContent = @"
;!@Install@!UTF-8!
Title="JR_Rest_API"
RunProgram="run.bat"
GUIMode="1"
;!@InstallEnd@!
"@
Set-Content -Path $CfgPath -Value $cfgContent -Encoding UTF8

# Locate 7-Zip
Write-Info "Locating 7-Zip"
$sevenZipExe = $null
$sevenZipSfx = $null

if ($SevenZipDir) {
  $sevenZipExe = Join-Path $SevenZipDir "7z.exe"
  $sevenZipSfx = Join-Path $SevenZipDir "7z.sfx"
}
if (-not $sevenZipExe -or -not (Test-Path $sevenZipExe)) {
  $candidates = @(
    "C:\\Program Files\\7-Zip\\7z.exe",
    "C:\\Program Files (x86)\\7-Zip\\7z.exe"
  )
  foreach ($c in $candidates) { if (Test-Path $c) { $sevenZipExe = $c; break } }
}
if (-not $sevenZipSfx -or -not (Test-Path $sevenZipSfx)) {
  $candidates = @(
    "C:\\Program Files\\7-Zip\\7z.sfx",
    "C:\\Program Files (x86)\\7-Zip\\7z.sfx"
  )
  foreach ($c in $candidates) { if (Test-Path $c) { $sevenZipSfx = $c; break } }
}

if (-not $sevenZipExe) {
  try { $cmd = Get-Command 7z.exe -ErrorAction Stop; $sevenZipExe = $cmd.Source } catch {}
}

if (-not $sevenZipExe -or -not (Test-Path $sevenZipExe)) {
  Write-Warn "7-Zip CLI not found. Will create a ZIP instead. Install 7-Zip and re-run to build an EXE."
  $zipOut = Join-Path $DistDir 'payload.zip'
  if (Test-Path $zipOut) { Remove-Item -Force $zipOut }
  Write-Info "Creating ZIP archive (fallback): $zipOut"
  if (Test-Path (Join-Path $DistDir 'payload')) {
    Compress-Archive -Path (Join-Path $DistDir 'payload\*') -DestinationPath $zipOut -Force
    Write-Host "ZIP ready: $zipOut" -ForegroundColor Green
  } else {
    Write-Err "Payload folder missing; cannot create ZIP."
    exit 1
  }
  exit 0
}
if (-not $sevenZipSfx -or -not (Test-Path $sevenZipSfx)) {
  Write-Warn "7z.sfx not found. Will build .7z archive only; cannot make self-extracting .exe."
}

# Build payload.7z
Write-Info "Creating 7z archive"
Push-Location $DistDir
& "$sevenZipExe" a -t7z -m0=lzma2 -mx=9 -mmt=on -r "$SevenZ" "payload\*" | Out-Null
Pop-Location

if (Test-Path $sevenZipSfx) {
  Write-Info "Building self-extracting EXE"
  if (Test-Path $OutExe) { Remove-Item -Force $OutExe }
  $cmd = "copy /b `"$sevenZipSfx`" + `"$CfgPath`" + `"$SevenZ`" `"$OutExe`""
  cmd.exe /c $cmd | Out-Null
  if (Test-Path $OutExe) {
    Write-Host "SUCCESS: $OutExe" -ForegroundColor Green
  } else {
    Write-Err "Failed building the EXE."
    exit 1
  }
} else {
  Write-Warn "Self-extractor module missing. Use the .7z at: $SevenZ"
}
