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
                'id',        // numeric ID for internal use
                'public_id', // ULID exposed to API clients
                'name',
                'location',
            ]);

        return response()->json($events);
    }

    public function show(Request $request, Event $event)
    {
        $u = $request->user();
        abort_unless(($event->user_id === $u->id) || ($u->is_admin ?? false), 403);

        return response()->json([
            'id'        => $event->id,
            'public_id' => $event->public_id,
            'name'      => $event->name,
            'location'  => $event->location,
        ]);
    }

    public function sessions(Request $request, Event $event)
    {
        $u = $request->user();
        abort_unless(($event->user_id === $u->id) || ($u->is_admin ?? false), 403);

        $sessions = $event->sessions()
            ->orderBy('session_date')
            ->get(['id', 'session_name as name', 'session_date']);

        return response()->json($sessions);
    }
}
