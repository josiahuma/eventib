<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class EventApiController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();

        $events = Event::query()
            ->when(!($u->is_admin ?? false), fn($q) => $q->where('user_id', $u->id))
            ->orderByDesc('id')
            ->get([
                'id',        // numeric ID for app
                'public_id', // expose ULID if we ever want to migrate
                'name',
                'location',
            ]);

        return response()->json($events);
    }

    public function sessions(Request $request, $eventId)
    {
        $u = $request->user();
        $event = Event::findOrFail($eventId);

        abort_unless(($event->user_id === $u->id) || ($u->is_admin ?? false), 403);

        $sessions = $event->sessions()
            ->orderBy('session_date')
            ->get(['id','session_name as name','session_date']);

        return response()->json($event->sessions()->get());
    }
}

