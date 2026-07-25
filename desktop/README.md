# Desktop app

Tauri shell (WebView) over the Symfony app served locally by **FrankenPHP in
worker mode** on `http://localhost:8765`, with a SQLite database
(`var/app.db`). No Docker, no MySQL, no host PHP required.

## Layout

- `Caddyfile` — FrankenPHP config: worker mode on `public/index.php`,
  `APP_ENV=desktop` (Dotenv then loads `.env` + `.env.desktop`).
- `php.ini` — extensions for the bundled binary (loaded via `PHPRC`, set by
  the start scripts).
- `start` / `start.ps1` — bootstrap: install vendors if missing, clear the
  `desktop` cache, update the SQLite schema, kill any zombie server on :8765,
  then launch FrankenPHP.
- `bin/` — drop the FrankenPHP binary here (gitignored), or have it on PATH.
- `src-tauri/` — the Tauri shell: starts the script, waits for `/login`,
  shows the window, kills the server tree on exit.

## Run

```sh
# Unix
desktop/start

# Windows
pwsh desktop/start.ps1
```

## Notes

- Worker mode is handled natively by `symfony/runtime`
  (`FrankenPhpWorkerRunner`, auto-detected via `FRANKENPHP_WORKER`) — no extra
  package needed.
- Console commands run through the same binary:
  `desktop/bin/frankenphp php-cli bin/console <cmd> --env=desktop`.
- Desktop-specific config: `when@desktop` blocks in `config/packages/` and
  `config/services_desktop.yaml` (auto-loaded by the kernel).
- Download FrankenPHP: <https://frankenphp.dev>
