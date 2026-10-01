<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventDiscoveryController extends Controller
{
    public function saved(Request $request)
    {
        $events = Event::query()->where('is_disabled', false)
            ->whereIn('id', DB::table('event_saves')->where('user_id', $request->user()->id)->select('event_id'))
            ->with(['sessions', 'categories' => fn ($q) => $q->where('is_active', true)])
            ->orderByDesc('id')->paginate(12);
        return view('discovery.saved', compact('events'));
    }

    public function save(Request $request, Event $event)
    {
        abort_if($event->is_disabled, 404);
        // Explicit desired state makes retries and double clicks safe.
        $data = $request->validate(['saved' => ['required', 'boolean']]);
        $key = ['user_id' => $request->user()->id, 'event_id' => $event->id];
        if ($data['saved']) {
            DB::table('event_saves')->insertOrIgnore($key + ['created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('event_saves')->where($key)->delete();
        }
        return back()->with('discovery_status', $data['saved'] ? 'Saved to My Plans.' : 'Removed from My Plans.');
    }

    public function share(Event $event)
    {
        abort_if($event->is_disabled, 404);
        $event->load(['sessions' => fn ($q) => $q->where('session_date', '>=', now())->orderBy('session_date')]);
        return view('discovery.share', compact('event'));
    }
}
