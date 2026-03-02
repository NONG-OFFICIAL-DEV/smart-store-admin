<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = Role::query()
            ->with([
                'permissions:id,code,group,description', // ✅ eager load permissions
            ]);

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy($request->get('sort_by', 'name'), $request->get('sort_order', 'asc'));

        $items = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Roles retrieved successfully.',
            'data'    => $items,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return Role::store($request);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $role = Role::with('permissions:id,code,group,description')
            ->findOrFail($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Role retrieved successfully.',
            'data'    => $role,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        return Role::store($request, $id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
