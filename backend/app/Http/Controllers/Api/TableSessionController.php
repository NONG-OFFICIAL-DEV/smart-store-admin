<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PaySessionException;
use App\Http\Controllers\Controller;
use App\Models\TableSession;
use App\Services\TableSessionService;
use Illuminate\Http\Request;

class TableSessionController extends Controller
{
    public function __construct(private TableSessionService $tableSessions)
    {
    }

    // ── PATCH /api/v1/table-sessions/{tableSession}/pay ─────────────────────
    // Pays off every unpaid order in the session at once and closes it —
    // see TableSessionService::paySession() for the actual rule. The Table
    // modal's PAY button sends the session id here, never a single order id.
    public function pay(Request $request, TableSession $tableSession)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,card,qr,transfer',
            'cash_tendered'  => 'nullable|numeric|min:0',
        ]);

        try {
            $receipt = $this->tableSessions->paySession(
                $tableSession->id,
                $validated,
                auth()->user()?->staff?->id
            );
        } catch (PaySessionException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded.',
            'data'    => $receipt,
        ]);
    }
}
