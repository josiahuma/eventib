<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserAdminController extends Controller
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

    public function index(Request $request)
    {
        $this->ensureAdmin();

        $q = trim((string) $request->query('q', ''));
        $users = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'q'));
    }

    public function toggleAdmin(User $user)
    {
        $this->ensureAdmin();

        // Optional: prevent demoting yourself accidentally
        if ($user->id === Auth::id() && $user->is_admin) {
            return back()->with('error', 'You cannot remove your own admin status.');
        }

        $user->is_admin = ! (bool) $user->is_admin;
        $user->save();

        return back()->with('success', 'Admin status updated.');
    }

    public function toggleDisabled(User $user)
    {
        $this->ensureAdmin();

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot disable your own account.');
        }

        $user->is_disabled = ! (bool) $user->is_disabled;
        $user->save();

        return back()->with('success', 'User status updated.');
    }
}