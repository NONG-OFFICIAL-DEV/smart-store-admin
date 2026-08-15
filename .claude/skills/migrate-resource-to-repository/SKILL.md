---
name: migrate-resource-to-repository
description: Extract one domain resource (Controller + Model) onto the Repository/Service/Resource pattern from photo-studio-saas, including the shared scaffolding on first use. Use when migrating any smart-store-admin resource off the current Model::store()-returns-JsonResponse pattern.
---

# Migrating a resource onto the target architecture

`CLAUDE.md` documents why this migration is happening: no Repository/Service layer exists yet, `BaseModel::store()` returns HTTP responses directly from the Model layer, and three inconsistent response envelopes coexist. This skill is the repeatable procedure — one resource at a time.

## Phase 0 — shared scaffolding (do this once, before the first resource)

Check whether `app/Repositories/Contracts/RepositoryInterface.php` already exists. If not, this is the first resource being migrated — port the shared base classes from `photo-studio-saas` first, adapted to this project (same UUID-PK convention, so the port is close to 1:1):

1. `app/Repositories/Contracts/RepositoryInterface.php` — `query()`, `all()`, `find()`, `findOrFail()`, `create()`, `update()`, `delete()`, `paginateServer(array $filters = [], int $perPage = 15)`.
2. `app/Repositories/Eloquent/BaseRepository.php` — implements the above, plus `$searchable` (columns array, override per repo), `applySearch()`/`applyFilters()` (no-op hook, override per repo)/`applySort()`. **Do not add any tenant-filtering logic here** — `TenantScope` is a global scope already active via `#[ScopedBy]` on the model, so every query the Repository builds is automatically tenant-scoped; adding manual filtering here would be redundant and risks double-filtering bugs.
3. `app/Services/BaseService.php` — just `__construct(protected RepositoryInterface $repository)`.
4. `app/Traits/ApiResponse.php` — `success()`/`created()`/`noContent()`/`error()`, all returning the **target** envelope: `{success, message, code, params, data, meta}`. This becomes the one shape every migrated controller uses — don't match any of the three legacy shapes already in the codebase.
5. `app/Providers/RepositoryServiceProvider.php` — register it in `bootstrap/providers.php`, bind each interface as you create it (one line per resource, added incrementally as each resource migrates — don't pre-bind resources that haven't been migrated yet).

Frontend equivalent, also once: a generic `AppTable.vue` (or add it via `@nong-official-dev/core` once that dependency is bumped past v1.2.14 — check with the user which they want, since this project currently pins an older version). It must speak the same filter contract as `paginateServer()`: receives `{ page, itemsPerPage, sortBy, sortDesc, search, ...filters }`, returns `{ items, total }`.

## Phase 1 — per resource (repeat for each)

1. **Read the current controller and model fully first.** Note: any business logic buried in the Model's `store()`/other static methods, any existing tests (none exist yet for most resources — if you add the Repository/Service, add a Feature test alongside it, following `tests/Feature/Security/TenantScopeTest.php`'s pattern of hand-built fixtures, no factories), any duplicate implementation (check `routes/api.php` for a `v2` counterpart — `Product` has one, others might too).
2. **Create `<Resource>RepositoryInterface`** extending nothing but declaring resource-specific methods beyond the base ones, if any are needed.
3. **Create `<Resource>Repository extends BaseRepository implements <Resource>RepositoryInterface`** — set `$searchable`, override `query()` for eager-loads, override `applyFilters()` for this resource's filter keys (read them straight off the current controller's hand-rolled `where`/`orWhere` calls — that logic is the spec).
4. **Create `<Resource>Service extends BaseService`** — one method per non-trivial operation. Anything that's currently inline logic in the Model's `store()` (side effects, related-record syncing, multi-model operations) moves here, not into the Repository.
5. **Create/update `<Resource>Resource`** (`Http/Resources/`) — explicit fields only, matching what the frontend currently actually consumes (check the frontend's `<resource>Service.js` and the page components using it, since there's no existing Resource to diff against for most models).
6. **Thin the controller** — inject the Service, `use ApiResponse`, delegate every action, respond via the Resource + envelope. Remove the `Model::store()` call sites for this resource once nothing else calls them (grep for `<Model>::store(` across the whole app first — `BaseModel::store()` is used broadly, so removing a model's override without checking every caller will break other controllers still calling it).
7. **Wire the frontend** — point the relevant list page at the generic table component with a `fetchFn` matching the new envelope's `data`/`meta.total` shape (it changed, since the old raw-paginator or ad-hoc envelope responses had a different shape).
8. **Verify**: run the resource's existing usage manually (no test suite to lean on for most resources yet) or add a Feature test. Confirm `TenantScope` still applies correctly (the Repository doesn't fight it) — a quick `tinker` check like the ones used to verify the original `TenantScope` fix (log in as two different tenant owners, compare counts) is the fastest sanity check.

## Risk notes (from the initial architecture audit — reread before picking the next resource)

- **`Order`** — `OrderController::store()` wraps ~120 lines in one `DB::transaction` (stock deduction + payment + loyalty points across multiple models). Migrate this one last, and preserve the transaction boundary exactly — don't split it across Repository calls in a way that loses atomicity.
- **`Product`** — has the live `v1`/`v2` duplication above. Decide up front whether this migration also resolves it.
- **Any resource whose Model overrides `BaseModel::store()`** — find every caller of that specific override (not just `BaseModel::store()` generically) before deleting it.
