<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Http\Request;

class EventApiController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();

        // Ownership: event.user_id == user.id OR admin
        $events = Event::query()
            ->when(!($u->is_admin ?? false), fn($q) => $q->where('user_id', $u->id))
            ->orderByDesc('id')
            ->get([
                'id',
                // If your field is 'title' not 'name', switch it here:
                'name',
                'location',
                // add anything useful for the app:
                // 'starts_at','ends_at'
            ]);

        return response()->json($events);
    }

    public function sessions(Request $request, Event $event)
    {
        $u = $request->user();
        abort_unless(($event->user_id === $u->id) || ($u->is_admin ?? false), 403);

        // Your schema uses session_name, session_date:
        $sessions = $event->sessions()
            ->orderBy('session_date')
            ->get(['id','session_name as name','session_date']);

        return response()->json($sessions);
    }
}
