<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventPayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayoutAdminController extends Controller
{
    private function ensureAdmin(): void
    {
        // Replace this with your own logic if you don’t have is_admin.
        abort_unless(Auth::user() && (bool)(Auth::user()->is_admin ?? false), 403);
    }

    public function index(Request $request)
    {
        $this->ensureAdmin();

        $status  = $request->query('status'); // processing|paid|failed|cancelled
        $search  = trim((string)$request->query('q'));
        $perPage = 20;

        $query = EventPayout::query()
            ->with(['event:id,public_id,name', 'event.user:id,name,email'])
            ->latest();

        if (in_array($status, ['processing','paid','failed','cancelled','canceled'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('event', fn ($e) => $e->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('event.user', fn ($u) => $u->where('email', 'like', "%{$search}%")
                                                         ->orWhere('name', 'like', "%{$search}%"));
            });
        }

        $payouts = $query->paginate($perPage)->withQueryString();

        $totals = (clone $query)->get();
        $totalCount     = $totals->count();
        $processingCnt  = $totals->where('status','processing')->count();
        $paidCnt        = $totals->where('status','paid')->count();
        $failedCnt      = $totals->where('status','failed')->count();
        $cancelledCnt   = $totals->whereIn('status',['cancelled','canceled'])->count();

        return view('admin.payouts.index', compact(
            'payouts',
            'status',
            'search',
            'totalCount',
            'processingCnt',
            'paidCnt',
            'failedCnt',
            'cancelledCnt'
        ));
    }

    public function updateStatus(Request $request, EventPayout $payout)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'status' => 'required|in:paid,failed',
        ]);

        $status = $validated['status'];

        $payout->status  = $status;
        $payout->paid_at = $status === 'paid' ? now() : null; // set when paid, clear when failed
        $payout->save();

        return back()->with('success', "Payout #{$payout->id} marked as {$status}.");
    }
}
