<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Display a paginated list of audit logs with optional filters.
     */
    public function index(Request $request)
    {
        $query = AuditLog::query()->with('user'); // eager load user to avoid N+1 problem

        // Optional filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        // Pagination
        $perPage = (int) $request->get('per_page', 20);

        return response()->json(
            $query->orderBy('created_at', 'desc')->paginate($perPage)
        );
    }

    /**
     * Display a single audit log with user details.
     */
    public function show($id)
    {
        $auditLog = AuditLog::with('user')->find($id);

        if (!$auditLog) {
            return response()->json([
                'status' => 'error',
                'message' => 'Audit log not found'
            ], 404);
        }

        return response()->json($auditLog);
    }
}
