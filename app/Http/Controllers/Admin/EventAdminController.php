<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventAdminController extends Controller
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
        $events = Event::query()
            ->with('user:id,name,email')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                          ->orWhere('category', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.events.index', compact('events', 'q'));
    }

    public function toggleDisabled(Event $event)
    {
        $this->ensureAdmin();

        $event->is_disabled = ! (bool) $event->is_disabled;
        $event->save();

        return back()->with('success', 'Event status updated.');
    }

    public function togglePromote(Event $event)
    {
        $this->ensureAdmin();

        $event->is_promoted = ! (bool) $event->is_promoted;
        $event->save();

        return back()->with('success', 'Event promotion updated.');
    }
}
