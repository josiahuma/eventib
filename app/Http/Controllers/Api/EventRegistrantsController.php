<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventUnlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class EventRegistrantsController extends Controller
{
    public function index(Request $request, Event $event)
    {
        $user = Auth::user();
        abort_unless($user, 401);

        $isAdmin = (bool) ($user->is_admin ?? false);
        $isOwner = ((int) $event->user_id === (int) $user->id);

        // Only owner/admin can view registrants in scanner
        abort_unless($isOwner || $isAdmin, 403);

        // ── Detect paid events (same logic as web) ──
        $hasPaidCategories = $event->categories()
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->exists();

        $isPaidEvent = $hasPaidCategories || (((float) ($event->ticket_cost ?? 0)) > 0);

        // ── Unlock gate only for FREE events (non-admin) ──
        $isUnlocked = true;
        if (!$isAdmin && !$isPaidEvent) {
            $isUnlocked = EventUnlock::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->whereNotNull('unlocked_at')
                ->exists();

            if (!$isUnlocked) {
                // Return a 200 so the app can show a friendly UI instead of "error"
                return response()->json([
                    'allowed' => false,
                    'reason' => 'Unlock required to view registrants for this free event.',
                    'event_id' => (int) $event->id,
                    'is_paid_event' => (bool) $isPaidEvent,
                    'is_unlocked' => (bool) $isUnlocked,
                    'count' => 0,
                    'attendees_total' => 0,
                    'guests_total' => 0,
                    'registrations' => [],
                ], 200);
            }
        }

        $q = $request->query('q');
        $limit = (int) $request->query('limit', 200);

        // For paid events: only show PAID
        // For free unlocked events: show FREE
        // (Admin/owner can still see both if you want, but your request was: only paid OR unlocked)
        $allowedStatuses = $isPaidEvent ? ['paid'] : ['free'];

        $registrations = DB::table('event_registrations')
            ->where('event_id', $event->id)
            ->whereIn('status', $allowedStatuses)
            ->when($q, function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('mobile', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        // Attach sessions selected for each registration (from pivot)
        $withSessions = Schema::hasTable('event_registrations_session');
        $registrationIds = $registrations->pluck('id')->all();
        $sessionsByReg = collect();

        if ($withSessions && count($registrationIds)) {
            $rows = DB::table('event_registrations_session')
                ->whereIn('event_registration_id', $registrationIds)
                ->get();

            $sessionsByReg = $rows->groupBy('event_registration_id')->map(function ($items) {
                return $items->map(fn ($r) => (int) $r->event_session_id)->values();
            });
        }

        $data = $registrations->map(function ($r) use ($sessionsByReg) {
            // Party breakdown: support either party_size OR adults/children columns
            $partySize = 1;

            if (isset($r->party_size) && (int)$r->party_size > 0) {
                $partySize = (int) $r->party_size;
            } else {
                $adults = max(0, (int) ($r->party_adults ?? 0));
                $children = max(0, (int) ($r->party_children ?? 0));
                $partySize = 1 + $adults + $children;
            }

            $partySize = max(1, $partySize);

            return [
                'id' => (int) $r->id,
                'name' => (string) ($r->name ?? ''),
                'email' => (string) ($r->email ?? ''),
                'mobile' => (string) ($r->mobile ?? ''),
                'status' => (string) ($r->status ?? 'registered'),

                // ✅ party numbers for scanner UI
                'party_size' => $partySize,
                'party_adults' => (int) ($r->party_adults ?? 0),
                'party_children' => (int) ($r->party_children ?? 0),

                'created_at' => (string) ($r->created_at ?? ''),
                'session_ids' => $sessionsByReg->get($r->id, collect())->all(),
            ];
        })->values();

        $registrantCount = (int) $data->count();
        $attendeesTotal = (int) $data->sum(fn ($r) => max(1, (int) ($r['party_size'] ?? 1)));
        $guestsTotal = max(0, $attendeesTotal - $registrantCount);

        return response()->json([
            'allowed' => true,
            'event_id' => (int) $event->id,
            'is_paid_event' => (bool) $isPaidEvent,
            'is_unlocked' => (bool) $isUnlocked,

            // ✅ counts for your header pills
            'count' => $registrantCount,
            'attendees_total' => $attendeesTotal,
            'guests_total' => $guestsTotal,

            'registrations' => $data,
        ]);
    }
}