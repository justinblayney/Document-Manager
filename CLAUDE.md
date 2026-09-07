# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Darkstar File Manager (plugin slug `darkstar-file-manager`, function/option prefix `dsfm_`) is a single-purpose WordPress plugin: a private document exchange between site admins and individual logged-in users. There is no build system, no dependency manager, and no test suite — this is plain procedural PHP loaded directly by WordPress, plus two small vanilla JS files and one CSS file enqueued as-is.

## Commands

There is no npm/composer/build/lint/test tooling in this repo. The only script is the packaging build:

```bash
bash bin/build.sh
```

Reads the version from the `Version:` header in `darkstar-file-manager.php`, stages a clean copy of the plugin (excluding `.git`, `.github`, `.claude`, `bin`, `dist`, `README.md`), and zips it to `dist/darkstar-file-manager-<version>.zip`. Run this after bumping the version header whenever a release zip is needed.

To verify a change, install/symlink the plugin directory into a local WordPress install's `wp-content/plugins/` and activate it — there's no automated test harness.

## Release checklist

When bumping the version, update it in three places that must stay in sync: `darkstar-file-manager.php` (`Version:` header), `readme.txt` (`Stable tag:` and the `== Changelog ==` / `== Upgrade Notice ==` sections). WordPress.org distribution is via SVN (separate from this git repo) — the git history here is the source of truth, not the deploy mechanism.

## Architecture

**Load order** — `darkstar-file-manager.php` is the only file WordPress loads directly. It defines the shared constants (`DSFM_UPLOAD_ROOT`, `DSFM_MAX_UPLOADS_PER_HOUR`), the shared helpers used by both front-end and admin code (`dsfm_protect_upload_dir()`, `dsfm_validate_upload()`), activation/uninstall behavior, asset enqueueing, and Polylang string registration — then requires the three files in `includes/` at the bottom. Those files register their own hooks on load; there's no central router.

- `includes/client-functions.php` — the entire front-end experience lives in one shortcode, `[dsfm_client_login]`: login form for logged-out visitors, and for logged-in users, upload form + delete + a combined listing split into "Documents for you" (admin-uploaded) vs "Your Uploaded Documents" (client-uploaded), all rendered from one buffered function. Also registers the `init` hook that serves authenticated downloads via a `dsfm_download` query arg.
- `includes/admin-functions.php` — the admin counterpart: a hidden admin page (`dsfm-view-user-docs`, registered under `users.php` but CSS-hidden from the menu, reached only via the "View Documents" row action WordPress adds to the Users list) for uploading/deleting/bulk-deleting one user's files, plus the parallel `admin_init` download handler for admin downloads.
- `includes/settings.php` — the options page (Settings → Darkstar File Manager) for `dsfm_upload_root`, `dsfm_max_file_size`, `dsfm_allowed_types`, including a "path detection helper" and a warning if the configured path is detected inside the web root (`DOCUMENT_ROOT`).

**Storage model** — no database tables. Each user gets a folder at `DSFM_UPLOAD_ROOT/<sanitize_file_name(user_login)>/`, containing the uploaded files (renamed `<timestamp>-<original-name>` via `wp_handle_upload`'s `unique_filename_callback`) plus a single `file-metadata.json` mapping stored filename → `{timestamp, uploaded_by}` (`uploaded_by` is `'admin'` or `'client'`; older entries may be a bare timestamp int — both call sites normalize this). Client-side and admin-side upload/delete/download logic is duplicated rather than shared (separate handlers in each `includes/` file), so a fix to one flow (e.g. validation, rate limiting, path-traversal checks) usually needs the matching fix in the other.

**Security invariants to preserve when touching upload/download/delete code** — these are the plugin's whole value proposition, so changes here need extra care:
- Files are never served by direct URL; every download goes through a nonce-verified handler (`init` hook for clients, `admin_init` for admins) that resolves the real path and checks it's still inside the user's own directory (`realpath()` + prefix check) before streaming.
- All destructive/mutating actions (upload, delete, bulk delete, settings save) verify a nonce first.
- Uploads are validated in `dsfm_validate_upload()` (shared by both flows): extension allowlist, WordPress's own `wp_check_filetype_and_ext()`, a MIME check built dynamically from `wp_get_mime_types()` plus a small alias table, and a ZIP-bomb guard capping uncompressed content at 512 MB.
- Per-user upload rate limiting (`DSFM_MAX_UPLOADS_PER_HOUR`, default 20) is tracked via a transient keyed by user ID and the current hour.
- `dsfm_protect_upload_dir()` writes a deny-all `.htaccess` + empty `index.php` into the upload root on activation and whenever a new user subfolder is created — but this only protects Apache; `includes/settings.php` explicitly warns when the configured path looks like it's inside the web root since Nginx ignores `.htaccess`.

**i18n** — all user-facing strings go through `__()`/`_e()`/etc. with text domain `darkstar-file-manager`, and are additionally registered with `pll_register_string()` in the main plugin file for Polylang's String Translations UI when Polylang is active. When adding new user-facing strings, add the corresponding `pll_register_string()` call too.
