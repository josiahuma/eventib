<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPayout;
use App\Models\User;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\Auth;

class AdminDashboardController extends Controller
{
    private function ensureAdmin(): void
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if (isset($user->is_admin) && (bool)$user->is_admin) return;

        $allowed = collect(explode(',', (string) env('ADMIN_EMAILS', '')))
            ->map(fn($e) => strtolower(trim($e)))->filter()->all();

        if (in_array(strtolower((string) $user->email), $allowed, true)) return;

        abort(403);
    }

    public function index()
    {
        $this->ensureAdmin();

        $stats = [
            'users_total'       => User::count(),
            'users_disabled'    => User::where('is_disabled', true)->count(),
            'events_total'      => Event::count(),
            'events_disabled'   => Event::where('is_disabled', true)->count(),
            'payouts_total'     => EventPayout::count(),
            'payouts_processing'=> EventPayout::where('status', 'processing')->count(),
            'payouts_paid'      => EventPayout::where('status', 'paid')->count(),
            'registrants_total'=> EventRegistration::count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}