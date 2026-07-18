// Prevents additional console window on Windows in release
#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

use std::path::PathBuf;
use std::process::{Child, Command};
use std::sync::Mutex;
use std::thread;
use std::time::Duration;
use tauri::Manager;

fn main() {
    let backend = Mutex::new(start_backend());
    wait_for_backend();

    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .setup(|app| {
            let window = app.get_webview_window("main").unwrap();
            window.show().unwrap();
            Ok(())
        })
        .on_window_event(move |_, event| {
            if let tauri::WindowEvent::CloseRequested { .. } = event {
                if let Ok(mut child) = backend.lock() {
                    kill_backend(&mut child);
                }
            }
        })
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}

/// Walks up from the executable to find the Symfony project root.
fn project_root() -> PathBuf {
    std::env::current_exe()
        .expect("failed to get executable path")
        .ancestors()
        .find(|p| p.join("composer.json").exists())
        .expect("project root not found (composer.json missing)")
        .to_path_buf()
}

#[cfg(target_os = "windows")]
fn start_backend() -> Child {
    let root = project_root();
    let script = root.join("desktop").join("start.ps1");

    Command::new("pwsh")
        .arg("-ExecutionPolicy")
        .arg("Bypass")
        .arg("-File")
        .arg(script)
        .current_dir(&root)
        .spawn()
        .expect("failed to start backend")
}

#[cfg(not(target_os = "windows"))]
fn start_backend() -> Child {
    let root = project_root();
    let script = root.join("desktop").join("start");

    Command::new(script)
        .current_dir(&root)
        .spawn()
        .expect("failed to start backend")
}

/// Waits for the local Symfony server to answer on the login page.
fn wait_for_backend() {
    for _ in 0..60 {
        if let Ok(response) = reqwest::blocking::get("http://localhost:8765/login") {
            if response.status().is_success() {
                return;
            }
        }
        thread::sleep(Duration::from_secs(1));
    }
    panic!("backend did not start after 60 seconds");
}

/// Stops the backend and its whole process tree.
///
/// On Windows the spawned process is pwsh, which itself started `php -S`:
/// a plain `kill()` would leave the PHP server orphaned and port 8765 busy.
/// `taskkill /T` kills the whole tree.
#[cfg(target_os = "windows")]
fn kill_backend(child: &mut Child) {
    let _ = Command::new("taskkill")
        .args(["/PID", &child.id().to_string(), "/T", "/F"])
        .output();
    let _ = child.wait();
}

#[cfg(not(target_os = "windows"))]
fn kill_backend(child: &mut Child) {
    // desktop/start ends with `exec php ...`: killing the process is enough.
    let _ = child.kill();
    let _ = child.wait();
}
