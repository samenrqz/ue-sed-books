# Copilot Instructions for UEsed Books

## Build, test, and lint commands

- This repository has no `composer.json`, `package.json`, PHPUnit config, or scripted lint/test/build commands.
- Run locally with XAMPP (Apache + MySQL), then open `index.html` or `login.php`.
- For quick PHP syntax checks on a single file, use:
  - `php -l listing.php`

## High-level architecture

- **Single-connection bootstrap**: `connect.php` is required by almost every PHP entry point and is responsible for DB connection plus schema bootstrap/migrations (`CREATE TABLE IF NOT EXISTS` and `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`).
- **Session-driven routing**:
  - Guest entry: `index.html`, `register.html`, `login.php`
  - Authenticated user area: `home.php`, `listing.php`, `history.php`, `account.php`, `about.php`
  - Admin area: `admin.php`, `users.php`, `books.php`, `transaction.php` (guarded by `$_SESSION['is_admin']`)
- **Role model**:
  - Super admin: hardcoded login `admin@gmail.com` / `admin` in `login.php`
  - Workspace admins: `admin{N}@gmail.com` with password `admin`, stored in `workspace_admins`
  - Regular users: records in `users`, password-hashed with `password_hash` / `password_verify`
- **Transactional flow**:
  - Listings are managed in `listing.php` (add/edit/delete books).
  - Purchase requests are created via AJAX `fetch('buy_request.php')`.
  - Seller approval/rejection happens via AJAX `fetch('update_request.php')` from `history.php`.
  - Admin transaction management is in `transaction.php`.
- **Schema compatibility behavior**: `buy_request.php` dynamically checks `transactions` columns (`book_id`, `buyer_id`, `message`) with `SHOW COLUMNS` and falls back to a legacy insert shape when columns are missing.

## Key conventions

- **Session keys are contract-level**: `user_id`, `first_name`, `last_name`, `email`, `is_admin`, `is_super_admin`. Keep names and semantics consistent when adding features.
- **Response style is mixed by endpoint type**:
  - Full-page form handlers typically use alerts/redirects (`<script>alert(...); window.history.back();</script>` or `header("Location: ...")`).
  - AJAX endpoints (`buy_request.php`, `update_request.php`, `check_email.php`) always return JSON with `success/message` or `exists`.
- **Use prepared statements for user input** (common pattern across auth, profile, listing, and admin CRUD).
- **File upload conventions**:
  - Book/admin assets in `images/`
  - User profile photos in `uploads/`
  - Naming patterns like `book_<uniqid>.<ext>`, `website_logo_<timestamp>.<ext>`, `user_<id>_<time>.<ext>`
- **Validation pattern is intentionally shared across pages**: `register.html` and `login.php` use the same validation CSS classes (`.val-*`, `.req-star`) and similar inline validation behavior; keep parity when changing form UX.
- **Admin UI is split across multiple entry files** (`admin.php`, `users.php`, `books.php`, `transaction.php`) but reuses common sidebar/profile/weather widgets and styling conventions.
