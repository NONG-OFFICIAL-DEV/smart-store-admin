<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * One "manage" permission per business module (covers create/update/delete
     * for that module's routes; .view/.delete stay separate only where they
     * already existed before this seeder). Tenants assign these to their own
     * custom roles via Role management — this table is the shared catalog.
     */
    private const PERMISSIONS = [
        'Branches'        => ['branches.manage'],
        'Users'           => ['users.manage'],
        'Roles'           => ['roles.manage'],
        'Staff'           => ['staff.manage'],
        'Shifts'          => ['shifts.manage'],
        'Menus'           => ['menus.manage'],
        'Categories'      => ['categories.manage'],
        'Products'        => ['products.manage'],
        'Tables'          => ['floor_plans.manage'],
        'Reservations'    => ['reservations.manage'],
        'Orders'          => ['orders.manage'],
        'Kitchen'         => ['kitchen.manage'],
        'Payments'        => ['payments.manage'],
        'Cash Drawers'    => ['cash_drawers.manage'],
        'Suppliers'       => ['suppliers.manage'],
        'Ingredients'     => ['ingredients.manage'],
        'Inventory'       => ['inventory.manage'],
        'Purchase Orders' => ['purchase_orders.manage'],
        'Customers'       => ['customers.manage'],
        'Promotions'      => ['promotions.manage'],
        'Reports'         => ['reports.view'],
        'Billing'         => ['billing.manage'],
    ];

    public function run(): void
    {
        $created = collect();

        foreach (self::PERMISSIONS as $group => $codes) {
            foreach ($codes as $code) {
                $created->push(
                    Permission::updateOrCreate(['code' => $code], ['group' => $group])
                );
            }
        }

        // ── Grandfather existing roles ──────────────────────────────────────────
        // Permission enforcement previously didn't exist at all (the middleware
        // was a no-op), so every existing role already had de-facto full access.
        // Attach every newly-seeded code to every pre-existing role so turning
        // enforcement on doesn't lock any current staff out of anything they
        // could already do. Roles created from here on start with none of these
        // and the tenant grants them deliberately — that's the point of RBAC.
        $allIds = Permission::pluck('id');
        Role::all()->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($allIds));
    }
}
