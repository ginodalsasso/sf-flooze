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
php bin/console doctrine:schema:update --force --env=desktop --no-interaction
if ($LASTEXITCODE -ne 0) {
    Write-Error "Error while updating the database schema."
    exit 1
}

# Kill old PHP servers still listening on port 8765. Otherwise a "zombie"
# server (started with another environment) keeps the port: the new server
# cannot bind and Tauri ends up querying the old one.
Get-NetTCPConnection -LocalPort 8765 -State Listen -ErrorAction SilentlyContinue |
    Select-Object -ExpandProperty OwningProcess -Unique |
    ForEach-Object {
        if ((Get-Process -Id $_ -ErrorAction SilentlyContinue).ProcessName -eq 'php') {
            Stop-Process -Id $_ -Force
        }
    }

# Start the PHP built-in web server with a dedicated router.
# The router forces $_SERVER/$_ENV because some PHP setups (variables_order
# without 'E') do not forward environment variables to the built-in server.
Write-Host "Starting local server on http://localhost:8765"
Write-Host "APP_ENV = $([Environment]::GetEnvironmentVariable('APP_ENV', 'Process'))"
Write-Host "DATABASE_URL = $([Environment]::GetEnvironmentVariable('DATABASE_URL', 'Process'))"
php -S localhost:8765 -t "$root/public" "$root/desktop/router.php"
