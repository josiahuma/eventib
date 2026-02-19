<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class EventRegistrantsController extends Controller
{
    public function index(Request $request, Event $event)
    {
        // Optional: only allow event owner / organizer to view
        // if ($event->user_id !== auth()->id()) abort(403);

        $q = $request->query('q'); // search

        $registrations = \DB::table('event_registrations')
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
            $rows = \DB::table('event_registrations_session')
                ->whereIn('event_registration_id', $registrationIds)
                ->get();

            $sessionsByReg = $rows->groupBy('event_registration_id')->map(function ($items) {
                return $items->map(fn ($r) => (int) $r->event_session_id)->values();
            });
        }

        $data = $registrations->map(function ($r) use ($sessionsByReg) {
            return [
                'id' => (int) $r->id,
                'name' => (string) ($r->name ?? ''),
                'email' => (string) ($r->email ?? ''),
                'mobile' => (string) ($r->mobile ?? ''),
                'status' => (string) ($r->status ?? 'registered'),
                'party_size' => (int) ($r->party_size ?? 1),
                'created_at' => (string) ($r->created_at ?? ''),
                'session_ids' => $sessionsByReg->get($r->id, collect())->all(),
            ];
        })->values();

        return response()->json([
            'event_id' => (int) $event->id,
            'count' => $data->count(),
            'registrations' => $data,
        ]);
    }
}
