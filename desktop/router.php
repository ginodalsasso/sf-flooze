<?php

/**
 * Router of the PHP built-in server for desktop mode (Tauri).
 *
 * It forces the "desktop" environment (SQLite database var/app.db) directly
 * into $_SERVER, $_ENV and via putenv(), because:
 *  - some PHP setups (variables_order without "E") leave $_ENV empty,
 *    which made the app fall back to APP_ENV=dev;
 *  - Dotenv never overrides a real environment variable, so these values
 *    win over .env, .env.local and friends, which point to other databases
 *    (MySQL, var/sf-flooze.db) where the user table does not exist.
 */

$projectDir = dirname(__DIR__);

// Security: this file must NEVER run outside the PHP built-in server.
// It lives outside public/, so it is not reachable on a production deploy;
// keep this guard anyway as defense in depth (copy, symlink, misconfiguration).
if ('cli-server' !== PHP_SAPI) {
    http_response_code(404);
    exit;
}

// APP_DEBUG=0: the desktop app is a packaged product; debug error pages
// would leak stack traces and environment variables in the WebView.
foreach (['APP_ENV' => 'desktop', 'APP_DEBUG' => '0', 'DATABASE_URL' => 'sqlite:///%kernel.project_dir%/var/app.db'] as $name => $value) {
    $_SERVER[$name] = $value;
    $_ENV[$name] = $value;
    putenv("$name=$value");
}

// Existing static files (assets, vendor, etc.) are served directly
// by the built-in server.
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ('/' !== $path && is_file($projectDir.'/public'.$path)) {
    return false;
}

// With a router, SCRIPT_FILENAME points to THIS file. But
// vendor/autoload_runtime.php requires $_SERVER['SCRIPT_FILENAME'] to find
// the front controller: point it back to index.php, otherwise the router
// gets included again and the runtime throws a TypeError.
$_SERVER['SCRIPT_FILENAME'] = $projectDir.'/public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require $projectDir.'/public/index.php';
