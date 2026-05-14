# Viewzy (Bagisto 2.4.x) — AI Agent Vibecode README

This repository is a **Bagisto 2.4.x** (Laravel 12) e-commerce app with a thin `app/` shell and most features living inside modular packages under `packages/Webkul/*`.

Use this README as an **operating manual** for an AI coding agent: where to look, what to edit, what not to touch, and how to safely implement changes.

---

## 1) Quick Start (Localhost Preview)

### Prereqs
- PHP 8.3+
- Composer
- MySQL/MariaDB
- Node.js (for Vite/assets)

### Typical first-time run
From repo root (same level as `artisan`):
1. `composer install`
2. Confirm `.env` DB settings are correct
3. `php artisan key:generate` (skip if `APP_KEY` already present)
4. `php artisan storage:link`
5. `php artisan migrate --seed`
6. `php artisan serve`

Open:
- Shop: `http://127.0.0.1:8000/`
- Admin: `http://127.0.0.1:8000/admin` (based on `APP_ADMIN_URL=admin`)

### Assets (CSS/JS)
Root Vite (minimal):
- `npm install`
- `npm run build` (or `npm run dev` for HMR)

Bagisto often has separate Admin/Shop asset pipelines. If the UI loads but styles/scripts are missing, run builds from package folders:
- `cd packages/Webkul/Admin && npm install && npm run build`
- `cd packages/Webkul/Shop && npm install && npm run build`

### When things look “stuck”
- `php artisan optimize:clear`

---

## 2) Repo Structure (Where Things Actually Live)

### Thin Laravel shell
- `app/` — minimal app glue (middleware/providers)
- `routes/web.php` — minimal; packages register most routes
- `config/` — global configuration (themes, concord modules, cache, etc.)
- `resources/` — root assets (Vite input is `resources/css/app.css` + `resources/js/app.js`)

### Core functionality lives in packages
- `packages/Webkul/<PackageName>/src/` — controllers, models, repositories, events, views, lang, routes, assets

Key idea: **When you want to change behavior, you usually change a package**.

---

## 3) “Do Not Edit” Zones (High break risk)

Avoid editing these unless the task explicitly requires it:
- `vendor/`
- `node_modules/`
- `storage/` (runtime output)
- `public/themes/*/build/` (Vite build output)
- `bootstrap/providers.php` and `config/concord.php` (module/provider wiring) — only touch if you fully understand provider registration.

Lockfiles:
- Don’t edit `composer.lock` unless dependency changes are intended.

---

## 4) Package Anatomy (Bagisto Convention)

A typical package directory:

```
packages/Webkul/<Package>/src/
  Config/               # system.php (admin settings), admin-menu.php, acl.php, etc.
  Contracts/            # interfaces for models
  Database/
    Migrations/
    Seeders/
    Factories/
  Http/
    Controllers/
    Requests/           # FormRequest validators
    Middleware/
  Listeners/            # event listeners
  Models/               # Eloquent models + Proxy classes
  Providers/            # ServiceProvider + ModuleServiceProvider + EventServiceProvider
  Repositories/         # data access layer (Prettus repository)
  Resources/
    assets/             # package JS/CSS/images (Vite)
    lang/<locale>/      # translations
    views/              # Blade templates
  Routes/
    admin-routes.php
    shop-routes.php
```

---

## 5) How to “Vibecode” Safely (Default Workflow)

When implementing a change:

1) **Identify the package**
- Search for a feature keyword or route name.
- Most code is in `packages/Webkul/*`.

2) **Follow the vertical slice** (entry → validation → business logic → persistence → view)
- Route file (admin/shop) → Controller → Request validation → Repository → Model/Contracts → View

3) **Make the smallest change that solves the problem**
- Prefer modifying an existing listener/controller/repository method over adding new surfaces.

4) **Run style + a focused test**
- Style: `vendor/bin/pint --dirty`
- Tests (pick the smallest suite relevant to your package):
  - `php artisan test --compact packages/Webkul/<Package>/tests`

5) **Translation safety**
- If you add translation keys under a package, they must exist in **all locales** for that package.
- Validate: `php artisan bagisto:translations:check`

---

## 6) Where to Change What (Common Tasks)

### A) Change a page / UI
- Shop views: `packages/Webkul/Shop/src/Resources/views/`
- Admin views: `packages/Webkul/Admin/src/Resources/views/`

If it’s a Vue component:
- Admin assets: `packages/Webkul/Admin/src/Resources/assets/`
- Shop assets: `packages/Webkul/Shop/src/Resources/assets/`

After frontend changes:
- Build from that package folder: `npm run build` (or `npm run dev`)

### B) Change HTTP behavior (controllers/validation)
- Controllers: `packages/Webkul/<Package>/src/Http/Controllers/`
- Validation: `packages/Webkul/<Package>/src/Http/Requests/`

Do NOT query Eloquent directly in controllers when a repository exists.

### C) Change data access
- Repositories: `packages/Webkul/<Package>/src/Repositories/`
- Models: `packages/Webkul/<Package>/src/Models/`
- Contracts: `packages/Webkul/<Package>/src/Contracts/`

Common pattern in Bagisto:
- Controllers call repositories.
- Repositories use Eloquent models.
- Cross-package references often type-hint **Contracts/Proxies**, not concrete models.

### D) Add/modify DB structure
- Migrations are typically in: `packages/Webkul/<Package>/src/Database/Migrations/`
- Run: `php artisan migrate`

### E) Add/modify an event reaction (Listeners)
- Listeners: `packages/Webkul/<Package>/src/Listeners/`
- Listener wiring often lives in: `packages/Webkul/<Package>/src/Providers/EventServiceProvider.php`

Bagisto uses string event names like:
- `catalog.product.update.after`
- `catalog.product.delete.before`

To locate wiring:
- Search for the event string or the listener class name.

---

## 7) Concrete Example: FPC Product Cache Listener

File:
- `packages/Webkul/FPC/src/Listeners/Product.php`

Wiring:
- `packages/Webkul/FPC/src/Providers/EventServiceProvider.php`

Observed behavior:
- On `catalog.product.update.after`, it forgets cached responses for URLs tied to the product (and related products).
- On `catalog.product.delete.before`, it finds the product by ID, then forgets caches.

Key methods:
- `afterUpdate($product)` → computes forgettable URLs → `ResponseCache::forget($urls)`
- `beforeDelete($productId)` → loads product → computes URLs → forget
- `getForgettableUrls($product)` → builds `['/'.$product->url_key, ...]`
- `getAllRelatedProducts($product)` → includes:
  - the product itself
  - parent configurable/bundle/grouped relations depending on type

Important dependencies:
- `Spatie\ResponseCache\Facades\ResponseCache`
- Product repositories in `packages/Webkul/Product/src/Repositories/*`

How to vibecode changes here:
- If you change product URL behavior (e.g., locale prefixes, channel URLs), update URL generation in `getForgettableUrls()`.
- If you add a new product type or new relationship that affects product page URLs, update `getAllRelatedProducts()` to include the impacted products.
- Keep return types stable (`array` of URL strings).

---

## 8) Routing: How to Find Endpoints

Bagisto routes are package-owned.

Where to look:
- Admin routes: `packages/Webkul/<Package>/src/Routes/admin-routes.php`
- Shop routes: `packages/Webkul/<Package>/src/Routes/shop-routes.php`

Workflow:
1) Search for a URI fragment (e.g., `/admin/products`) in `packages/Webkul/**/Routes/*.php`
2) Jump to controller action
3) Follow into repository/service

---

## 9) Themes / Assets / Vite

Root Vite config:
- `vite.config.js` uses inputs `resources/css/app.css` and `resources/js/app.js`

Themes live under:
- `public/themes/` (compiled output)
- `packages/Webkul/*/src/Resources/assets/` (source)

Rules:
- Don’t hand-edit `public/themes/*/build/*` (generated).
- Build from the relevant package directory after changing package assets.

---

## 10) Translations (CI-sensitive)

Translations are stored under package:
- `packages/Webkul/<Package>/src/Resources/lang/<locale>/*.php`

Rule:
- If you add/remove translation keys, you must update all locales for that package.

Validation:
- `php artisan bagisto:translations:check`

---

## 11) Testing & Style

### Style (Pint)
- Check/fix only touched files: `vendor/bin/pint --dirty`
- Check only: `vendor/bin/pint --test`

### Tests (Pest)
Examples:
- All tests: `php artisan test --compact`
- Package tests: `php artisan test --compact packages/Webkul/<Package>/tests`

E2E (Playwright) exists per package (Admin/Shop) and typically requires a running server + seeded DB.

---

## 12) Debugging Checklist (Fast)

When a change “doesn’t show up”:
- Clear caches: `php artisan optimize:clear`
- Rebuild assets (root and/or package): `npm run build`
- Confirm correct URL: `APP_URL` and `APP_ADMIN_URL` in `.env`
- Check logs: `storage/logs/laravel.log`

When migrations fail:
- Confirm DB credentials in `.env`
- Ensure DB exists
- Re-run: `php artisan migrate --seed`

---

## 13) Agent Rules of Engagement (High-signal)

### Preferred edit locations
- `packages/Webkul/**/src/**` for feature code
- `packages/Webkul/**/src/Resources/**` for UI, lang, assets

### Avoid unless necessary
- `bootstrap/providers.php`
- `config/concord.php`

### Change discipline
- Don’t reformat unrelated code.
- Don’t add new dependencies without explicit approval.
- Keep edits package-scoped if possible.

### How to answer “where do I change X?”
When asked to change a feature, your agent should respond with:
- The exact file path(s) likely involved
- The entry point (route/controller/listener)
- The supporting layer (request/repository/model/view)
- The validation command(s) to run afterward

---

## 14) Practical “Find It Fast” Search Queries

Use these queries when navigating:
- Find an event listener mapping: search for the event string (e.g., `catalog.product.update.after`)
- Find a controller for a route: search for route name/URI in `packages/Webkul/**/Routes/*.php`
- Find a view: search for `view(` or Blade include name
- Find repository usage: search for `Repository` injection in controllers/listeners

---

## 15) Reference Entry Points (Common)

- Root HTTP entry: `public/index.php`
- Laravel kernel/bootstrap: `bootstrap/app.php`
- App key/env: `.env`
- Root routes (minimal): `routes/web.php`
- Bagisto package code: `packages/Webkul/`

---

## 16) Minimal “Done” Checklist (Before handing back)

- `vendor/bin/pint --dirty`
- Affected tests pass (at least package-level)
- If translations changed: `php artisan bagisto:translations:check`
- Local manual check via `php artisan serve`
