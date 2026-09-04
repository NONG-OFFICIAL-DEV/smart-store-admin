# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

Smart Store Admin — a multi-tenant, multi-branch POS admin platform covering restaurant, mart/retail, and coffee-shop business types (`BusinessType`). Laravel 12 API backend (`backend/`) + Vue 3.5/Vuetify 3 SPA frontend (`frontend/`), PostgreSQL, JWT auth (`tymon/jwt-auth`, guard `api`). Large surface area: ~60 controllers, ~61 models covering staff/roles/shifts, products/categories/modifiers, floor tables/reservations, orders/kitchen tickets, payments/cash drawers, suppliers/inventory/purchase orders, customers/loyalty, promotions, and reporting.

The backend is being migrated resource-by-resource onto a Repository → Service → Resource/FormRequest → Controller architecture (mirroring a sibling project, `photo-studio-saas`), with one standard response envelope and server-driven pagination. Check `.claude/skills/migrate-resource-to-repository/SKILL.md` for the extraction procedure before touching a resource that hasn't migrated yet — don't assume any given controller already follows the target pattern without checking it directly (see "Migration status" below).

## Commands

### Backend (`backend/`)

```bash
composer dev               # php artisan serve + queue:listen + pail + vite + reverb:start, concurrently
php artisan test            # real PostgreSQL — sqlite can't create this schema (jsonb,
                             # gen_random_uuid(), timestampTz columns)
php artisan test --filter=TestClassName
php artisan migrate
php artisan tinker          # fastest way to check model/scope behavior directly
```

Create the test DB once with `createdb smart_store_test`; `phpunit.xml` already points at it.

Test convention: plain `RefreshDatabase` + hand-built fixtures (no factories except `UserFactory`). Feature tests live under `tests/Feature/<Resource>/`, one `*ServiceTest.php` per migrated resource, generally calling the Service directly via `$this->app->make(...)` with `Auth::login($user)` rather than hitting HTTP routes — see any existing `tests/Feature/*/​*ServiceTest.php` for the pattern. `tests/Feature/Security/TenantScopeTest.php` is the regression test for tenant isolation itself — if it starts failing, treat it as a real regression.

### Frontend (`frontend/`)

```bash
npm run dev        # vite
npm run build
npm run lint        # eslint . --ext .js,.ts,.vue (no --fix by default)
```

No `format` script despite `prettier` being a dependency. No frontend test runner — verify UI changes by running the dev server and exercising the feature directly.

### Docker

Root `docker-compose.yml` — `backend_service` (supervisord-managed), `webserver` (nginx, port 84), `frontend`. First run needs the usual `key:generate`/`jwt:secret`/`migrate` inside the backend container.

## Architecture

### Multi-tenancy

`App\Models\Scopes\TenantScope` (registered per-model via the `#[ScopedBy(TenantScope::class)]` attribute — **not automatic**, every tenant-owned model needs it explicitly; check which base class a model uses, since inheritance is mixed between `BaseModel` and plain `Model`) resolves the current user's tenant (`$user->ownedTenant` for an owner, `$user->staff->tenant_id` for staff) and applies one of, checked in this order:

1. **`INDIRECT_TENANT_RELATIONS` map** (`TenantScope::class` constant) — tables with neither `tenant_id` nor a reliable `branch_id`, scoped via a parent relation instead: `modifier_options => group`, `coupons => promotion`, `customer_addresses => customer`, `loyalty_transactions => customer`. Add new tables of this shape here rather than writing a new branch.
2. **Table-specific special cases**: `activity_logs` (plain `tenant_id = X`, no `orWhereNull` — null here means "no tenant context," not "shared"), `categories` (via the `category_tenant` pivot), `refunds` (via `whereHas('payment', ...)` against the tenant's branch IDs).
3. **Generic column-shape fallback**: `tenant_id = X orWhereNull tenant_id` (nullable `tenant_id` means "shared across every tenant" — true for `roles.is_system` templates, not universally true elsewhere; decide deliberately per table), or `whereIn(branch_id, tenantBranchIds)` for branch-scoped tables.

Super admins (`is_super_admin`) bypass the scope entirely. The scope is schema-driven (checks the target table's actual columns, cached 1hr) — a safe no-op on global catalog tables (`permissions`, lookup tables) with neither column, but also means a migration renaming `tenant_id` silently disables scoping with no error.

Console commands/jobs run with no authenticated user, so the scope no-ops entirely — any command/job touching tenant data must filter explicitly.

**Adding a new tenant-owned table**: give it `#[ScopedBy(TenantScope::class)]`, decide explicitly what `NULL` means for its tenant/branch column if nullable, and if it's only indirectly tenant-owned (no `tenant_id`/reliable `branch_id` of its own), add it to `INDIRECT_TENANT_RELATIONS` rather than writing a new branch.

### Response envelope

Standard shape via `App\Traits\ApiResponse`: `{success, message, code, params, data, meta, errors}`. `errors` is top-level (not nested under `meta`) — several frontend dialogs read `err.response.data.errors` directly. Use `success()`, `created()` (201), `noContent()`, `error()`.

Resources not yet migrated (see below) still return through `BaseModel::store()` (a static helper that persists **and** returns a `JsonResponse` directly from the Model layer — `{success: true, data}`/`{success: true, message}`) or other ad-hoc shapes. Don't assume a controller uses the standard envelope without checking; when migrating a resource, always move it onto `ApiResponse`, never invent a fourth shape.

### Repository/Service layer

Scaffolding: `app/Repositories/Contracts/RepositoryInterface.php`, `app/Repositories/Eloquent/BaseRepository.php` (no manual tenant filtering needed — `TenantScope` is already a global scope, so every query is automatically tenant-scoped; `paginateServer(array $filters)` is the standard list contract — `search`/`sortBy`/`sortDesc`/`perPage`/arbitrary filters, with overridable `applySearch()`/`applyFilters()`/`applySort()` hooks), `app/Services/BaseService.php`, `app/Providers/RepositoryServiceProvider.php` (`$repositoryBindings` — check this file directly for the current authoritative list of migrated resources, it changes often).

**Migrated onto Repository/Service/FormRequest/Resource** (thin controllers, `ApiResponse` envelope, real Feature tests): Customer, Supplier, Ingredient, Staff, Role, Permission, Shift, StaffShift, Branch, BranchHour, BranchMenu, BranchProductOverride, Table, Reservation, FloorPlan, Menu, Category, ModifierGroup, ModifierOption, Promotion, Coupon, User, ActivityLog/AuditLog, CustomerAddress, CashDrawer, InventoryStock, InventoryTransaction, KitchenDisplayTicket, LoyaltyTransaction, Notification, BusinessType, Plan, PurchaseOrder, MartPurchaseOrder, DailySalesSummary, Tenant, Product.

**Deliberately deferred, not yet migrated** — check with the user before migrating any of these, they carry real behavioral/business risk beyond a mechanical refactor:
- `Order` (+ `OrderItem`) — checkout/transaction core, touched by both POS flows and reporting; migrate last.
- `ProductVariant`, `ProductUnit`, `ProductRecipe` — nested under `products/{product}/...`, `ProductVariant::store()` has cross-record business logic (unsetting other variants' `is_default`). Safe to migrate now that `Product` itself is done; not yet done.
- `StockAdjustmentController` — reconciles Mart's own stock system (`products.stock_quantity`+`stock_movements`) against the restaurant ingredient/inventory system; cross-system reconciliation risk.
- `TenantSubscriptionController`, `BillingController` — billing-adjacent.
- `CoffeePOSorderController`, `MartPosController`, `HospitalityPosController` — live POS checkout transaction logic.
- `MartProductController` — Mart's own product listing, separate from the unified `Product`/`ProductController`.

**Confirmed not CRUD resources — do not attempt to migrate onto Repository/Service, they don't fit the pattern:**
- `DigitalMenuController` — public, unauthenticated, read-only QR-menu aggregation across Branch/Table/Product/Category/Menu. No model of its own.
- `DashboardController`, `AdminDashboardController` — reporting aggregations.
- `OrderExportController` — single-purpose XLSX export.
- `MartProductPerformanceController`, `MartPurchaseReportController` — single-`index()` reporting endpoints. `MartProductPerformanceController` resolves tenant/branch via the shared `App\Traits\ResolvesBranchContext` trait (auto-resolves a single-branch owner's branch, cleanly rejects a multi-branch owner with no explicit `branch_id` instead of fataling) — see `tests/Feature/Reports/ResolvesBranchContextTest.php`. **`MartPurchaseReportController` does not use it and has its own live gap**: `$tenantId` is commented out entirely (no tenant scoping beyond whatever `MartPurchaseOrder`'s own global scope provides), and `branch_id` is used as-is from the request with no fallback — an owner calling it without an explicit `branch_id` gets a silently all-empty report (`branch_id = null` matches no real branch) rather than a crash or a clear error. Not fixed.

**`Api\V1` namespace**: `AuditLogController`, `UserController`, `ShiftAssignmentController` live under `App\Http\Controllers\Api\V1` (physical path `app/Http/Controllers/Api/V1/`), matching the `/v1/...` URL prefix. Every other controller is still flat under `App\Http\Controllers\Api`. Check the actual `use` import in `routes/api.php` before assuming a controller's namespace — the two coexist and there's no rule for which one a given file is in beyond "has it been moved yet."

### Known schema/model quirks worth knowing before you hit them

- **`Category::$fillable` includes `menu_id`** and the model has a `menu()` belongsTo, but no `menu_id` column actually exists on `categories` — tenant linkage for categories goes through the `category_tenant` pivot instead (`categories.tenants()` belongsToMany). Setting `menu_id` silently no-ops (mass-assignment to a nonexistent column). Not fixed.
- **Schema drift is possible**: the `migrations` table can have more recorded rows than files present in `database/migrations/` (a migration was run and later deleted from disk). If you hit a "column does not exist" surprise on a column that clearly exists in dev, compare `SELECT count(*) FROM migrations` against the file count before assuming your checkout is stale — add a new `Schema::hasColumn()`-guarded migration to formalize any drift found, don't hand-edit the schema.
- **`exists:table,column` in a FormRequest bypasses `TenantScope`** (it's a raw DB query, not an Eloquent lookup) — for any tenant-owned referenced table, resolve through the model (e.g. `Model::findOrFail()`) inside the Service instead, so a cross-tenant id gets a proper 404/validation error rather than silently passing.
- **A resource reachable through a URL with more than one bound model is not automatically cross-validated by Laravel** — e.g. `modifier-groups/{group}/options/{option}` doesn't itself confirm `option` actually belongs to `group`. `TenantScope` closes the cross-tenant case; add an explicit `assertBelongsToX()` check in the controller/service for the same-tenant "right id, wrong parent in the URL" case.
- **PHP's `$fillable` whitelist silently drops unknown keys** on `create()`/`update()` — no error. If a frontend form has a bound field with no visible effect, check `$fillable` and the real DB columns before assuming the bug is elsewhere.

### Known gaps / follow-ups (real, not yet actioned)

- **Rotate the Telegram bot token** that was previously hardcoded in `NotificationService.php` and is already committed to git history — removing the line doesn't remove it from history. User action only (via @BotFather).
- `PurchaseOrderDialog.vue`'s edit form lets a user edit PO line items, but the backend has only ever processed `items` on create, never on update — edits to items are silently discarded server-side. Left alone deliberately (changing ordered-vs-received-quantity semantics on an in-flight PO is a real business decision).
- `CouponController::validate`/`CouponController::apply` are routed (`POST coupons/validate`, `POST orders/{order}/apply-coupon`) but don't exist on the controller — guaranteed 500 if ever called. Frontend never calls either. Revisit when `Order` is migrated (checkout-transaction logic, not coupon CRUD).
- Reporting gaps: `DailySalesSummaryController` has no `topCustomers`/`revenue`/`staffReport`, and `GET reports/inventory` has no controller method — none of these have any implementation anywhere in the codebase to base a build on (no spec, no waiting consumer). Treat as a real feature request if wanted, not a migration task.
- `customers` no longer has `gender`/`date_of_birth`/`preferred_language` (dropped — they were captured but never used anywhere beyond display). `CustomerDialog.vue`'s `source` dropdown no longer offers the invalid `social` value either.

### Frontend

- API layer convention: `src/api/<resource>Service.js` — plain exported functions over one shared axios instance, e.g. `getAllProductsApi = filters => http.get('/v1/products', { params: filters })`.
- A migrated resource's frontend store/service file needs updating for the new envelope even if the UI itself doesn't change yet: list responses are `res.data.data` (flat array) + `res.data.meta` (pagination), single-resource responses are `res.data.data`.
- No generic server-driven table component exists for most list pages yet — most hand-roll their own `v-data-table` + pagination state. `@nong-official-dev/core`'s `AppTable` (server-driven: `headers`, `fetch-fn` receiving `{page, perPage, sortBy, sortDesc, search, ...filters}` and returning `{items, total}`, a deep-watched `filters` object) has been adopted on `UserManagement.vue`, `AuditLogPage.vue`, and `TenantView.vue` so far — check a given page directly rather than assuming either pattern app-wide. Gotcha: any header marked `sortable: true` must be a real DB column — `BaseRepository::applySort()` does a bare `orderBy()` with no whitelist, so sorting by a computed accessor 500s.
- `@nong-official-dev/core` is on `^3.1.1`. The old imperative `$confirm({...})`/`$notif(...)` global API was fully removed in that line, replaced by `useAppUtils`/a `ConfirmDialog` component with no back-compat shim. The migration off the old local `@/composables/useAppUtils` wrapper is **in progress, not finished** — check which import a given file uses before assuming either pattern.
- `3.0.0`+ of `@nong-official-dev/core` peer-requires `vue-i18n: ^11.0.0` — check the installed version before assuming that gap is resolved.
- **Nav visibility vs. in-page role checks**: `src/config/sidebarMenu.js` gates entire nav groups with a parent-level `visible: ctx => ctx.isSuperAdmin` (e.g. the whole `system-administration` group — Users, Roles, Permissions, Activity Log). A tenant owner never sees or navigates to those pages at all, even though some of the underlying backend permissions (e.g. `roles.manage`) are still technically granted to Owner. Before adding an `isSuperAdmin()`/`isTenantUser` guard inside a page or on an individual button, check `sidebarMenu.js` first — if the whole page is already nav-gated to super-admin-only, an additional in-component check is redundant dead code, not defense-in-depth.
