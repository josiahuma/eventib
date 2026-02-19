<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class EventRegistrantsController extends Controller
{
    public function index(Request $request, Event $event)
    {
        // 1) Determine if event is paid (IMPLEMENT THIS)
        // Example: ticket categories table with active paid categories
        $isPaidEvent = DB::table('event_registration_items') // <-- replace with your ticket categories table if different
            ->where('event_id', $event->id)
            ->whereNotNull('amount_minor') // <-- replace logic
            ->where('amount_minor', '>', 0)
            ->exists();

        // 2) Determine if event is unlocked (IMPLEMENT THIS)
        // Easiest approach: boolean column on events (recommended)
        $unlocked = false;
        if (Schema::hasColumn('events', 'is_unlocked')) {
            $unlocked = (bool) $event->is_unlocked;
        }

        // OR: if you store unlock payments in a table, use that instead:
        // $unlocked = DB::table('event_unlocks')
        //     ->where('event_id', $event->id)
        //     ->where('status', 'paid')
        //     ->exists();

        // 3) Gate access
        if (!$isPaidEvent && !$unlocked) {
            // Option A: return 200 with allowed=false (best UX for app)
            return response()->json([
                'allowed' => false,
                'reason' => 'Unlock required to view registrants for free events.',
                'is_paid_event' => false,
                'unlocked' => false,
                'event_id' => (int) $event->id,
                'count' => 0,
                'attendees_total' => 0,
                'guests_total' => 0,
                'registrations' => [],
            ], 200);

            // Option B: enforce strictly:
            // abort(403, 'Unlock required to view registrants for free events.');
        }

        $q = $request->query('q'); // search

        $registrations = DB::table('event_registrations')
            ->where('event_id', $event->id)
            ->when($q, function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                       ->orWhere('email', 'like', "%{$q}%")
                       ->orWhere('mobile', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->limit((int) $request->query('limit', 200))
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
            $partySize = (int) ($r->party_size ?? 1);
            if ($partySize < 1) $partySize = 1;

            return [
                'id' => (int) $r->id,
                'name' => (string) ($r->name ?? ''),
                'email' => (string) ($r->email ?? ''),
                'mobile' => (string) ($r->mobile ?? ''),
                'status' => (string) ($r->status ?? 'registered'),
                'party_size' => $partySize,
                'created_at' => (string) ($r->created_at ?? ''),
                'session_ids' => $sessionsByReg->get($r->id, collect())->all(),
            ];
        })->values();

        // ✅ Correct totals for scanner
        $registrantCount = $data->count(); // registrations rows
        $attendeesTotal = $data->sum('party_size'); // includes registrant + guests
        $guestsTotal = max(0, $attendeesTotal - $registrantCount);

        return response()->json([
            'allowed' => true,
            'reason' => null,
            'is_paid_event' => $isPaidEvent,
            'unlocked' => $unlocked,
            'event_id' => (int) $event->id,
            'count' => $registrantCount,
            'attendees_total' => (int) $attendeesTotal,
            'guests_total' => (int) $guestsTotal,
            'registrations' => $data,
        ]);
    }
}