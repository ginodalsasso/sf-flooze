$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

if (-Not (Test-Path ".env.desktop")) {
    Write-Error "Error: .env.desktop not found."
    exit 1
}

# Load desktop-mode environment variables
Get-Content ".env.desktop" | ForEach-Object {
    if ($_ -match '^\s*#' -or $_ -match '^\s*$') { return }
    $parts = $_ -split '=', 2
    if ($parts.Length -eq 2) {
        $name = $parts[0].Trim()
        $value = $parts[1].Trim()
        # Strip surrounding quotes, like `source` does in bash
        $value = $value -replace '^["'']|["'']$'
        [Environment]::SetEnvironmentVariable($name, $value, "Process")
    }
}

# Load the desktop PHP configuration (extensions for the bundled binary)
[Environment]::SetEnvironmentVariable('PHPRC', "$root\desktop", 'Process')

# Locate the FrankenPHP binary: desktop/bin first, then PATH.
$frankenphp = @("$root\desktop\bin\frankenphp.exe", "$root\desktop\bin\frankenphp") |
    Where-Object { Test-Path $_ } |
    Select-Object -First 1
if (-Not $frankenphp) {
    $cmd = Get-Command frankenphp -ErrorAction SilentlyContinue
    if ($cmd) { $frankenphp = $cmd.Source }
}
if (-Not $frankenphp) {
    Write-Error "FrankenPHP binary not found. Download it from https://frankenphp.dev and place it in desktop/bin/ (or add it to your PATH)."
    exit 1
}

# Install Composer dependencies if needed
if (-Not (Test-Path "vendor/autoload.php")) {
    Write-Host "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
}

# Clear the desktop cache: a stale cache (built with APP_ENV=dev) could
# point the app to the wrong database.
if (Test-Path "var/cache/desktop") {
    Write-Host "Clearing desktop cache..."
    Remove-Item -Recurse -Force "var/cache/desktop" -ErrorAction SilentlyContinue
}

# Prepare the local SQLite database
# var/app.db is created automatically on first connection.
New-Item -ItemType Directory -Force -Path "var" | Out-Null
Write-Host "Updating SQLite schema (var/app.db)..."
& $frankenphp php-cli bin/console doctrine:schema:update --force --env=desktop --no-interaction
if ($LASTEXITCODE -ne 0) {
    Write-Error "Error while updating the database schema."
    exit 1
}

# Kill old servers still listening on port 8765. Otherwise a "zombie"
# server (started with another environment) keeps the port: the new server
# cannot bind and Tauri ends up querying the old one.
Get-NetTCPConnection -LocalPort 8765 -State Listen -ErrorAction SilentlyContinue |
    Select-Object -ExpandProperty OwningProcess -Unique |
    ForEach-Object {
        if ((Get-Process -Id $_ -ErrorAction SilentlyContinue).ProcessName -in @('php', 'frankenphp')) {
            Stop-Process -Id $_ -Force
        }
    }

# Start FrankenPHP in worker mode (see desktop/Caddyfile).
Write-Host "Starting FrankenPHP on http://localhost:8765"
Write-Host "APP_ENV = $([Environment]::GetEnvironmentVariable('APP_ENV', 'Process'))"
Write-Host "DATABASE_URL = $([Environment]::GetEnvironmentVariable('DATABASE_URL', 'Process'))"
& $frankenphp run --config "$root\desktop\Caddyfile"
