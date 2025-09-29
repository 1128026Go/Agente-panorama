# Setup script for project (PowerShell)
# - hace backup de composer.json
# - ejecuta composer update (si composer está disponible)
# - copia .env.example a .env si no existe
# - ejecuta npm install (si npm está disponible)

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
Write-Host "Running setup in: $root"

# Backup composer.json
$composer = Join-Path $root "..\composer.json" | Resolve-Path -ErrorAction SilentlyContinue
if (-not $composer) {
    $composer = Join-Path $root "composer.json"
}
$composerPath = (Resolve-Path $composer -ErrorAction SilentlyContinue).ProviderPath
if (Test-Path $composerPath) {
    $backup = "$composerPath.bak"
    Copy-Item -Path $composerPath -Destination $backup -Force
    Write-Host "Backup created: $backup"
} else {
    Write-Host "No composer.json found at expected path: $composerPath"
}

# Composer update
if (Get-Command composer -ErrorAction SilentlyContinue) {
    try {
        Write-Host "Running composer update..."
        composer update
    } catch {
        Write-Host "composer update failed: $_"
    }
} else {
    Write-Host "composer not found in PATH. Skipping composer update."
}

# Copy .env.example to .env if missing
$envExample = Join-Path $root "..\.env.example"
if (-not (Test-Path $envExample)) { $envExample = Join-Path $root ".env.example" }
$envDest = Join-Path $root "..\.env"
if (-not (Test-Path $envDest)) {
    if (Test-Path $envExample) {
        Copy-Item -Path $envExample -Destination $envDest
        Write-Host "Copied .env.example to .env"
    } else {
        Write-Host ".env.example not found; please create .env manually."
    }
} else {
    Write-Host ".env already exists; skipping copy."
}

# npm install
if (Get-Command npm -ErrorAction SilentlyContinue) {
    try {
        Write-Host "Running npm install..."
        npm install
    } catch {
        Write-Host "npm install failed: $_"
        Write-Host "If PowerShell blocks scripts, try running this script in CMD or ensure execution policy allows RemoteSigned."
    }
} else {
    Write-Host "npm not found in PATH. Skipping npm install."
}

Write-Host "Setup script finished."
