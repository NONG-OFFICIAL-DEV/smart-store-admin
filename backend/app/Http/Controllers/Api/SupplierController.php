<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\TenantResolver;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(
        private TenantResolver $tenantResolver
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 10), 100);
        $query = Supplier::query();
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        }
        $query->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'));
        $items = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Suppliers retrieved successfully.',
            'data'    => $items,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:150',
            'contact_person' => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string',
            'payment_terms'  => 'nullable|string|max:100',
            'is_active'      => 'boolean',

            // Super admin only — optionally pass tenant_id
            'tenant_id'      => 'sometimes|uuid|exists:tenants,id',
        ]);

        // ── Resolve tenant_id server side ─────────────────────────────────────
        $tenantId = $this->tenantResolver->resolve($request);

        $supplier = Supplier::create([
            'tenant_id'      => $tenantId,
            'name'           => $request->name,
            'contact_person' => $request->contact_person,
            'phone'          => $request->phone,
            'email'          => $request->email,
            'address'        => $request->address,
            'payment_terms'  => $request->payment_terms,
            'is_active'      => $request->boolean('is_active', true),
        ]);

        return response()->json(['success' => true, 'data' => $supplier], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        return Supplier::store($request, $id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $record = Supplier::find($id);

        if (!$record) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product removed'
        ]);
    }
}
