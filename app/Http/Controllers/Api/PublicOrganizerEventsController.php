<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublicOrganizerEventsController extends Controller
{
    public function index(Request $request, string $organizer)
    {
        // Optional simple key protection (set in config/services.php)
        $key = (string) $request->query('key', '');
        $expected = (string) config('services.eventib_public_feed.key');

        if ($expected !== '' && !hash_equals($expected, $key)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $limit = (int) $request->query('limit', 6);
        $limit = $limit > 0 ? min($limit, 30) : 6;

        // ✅ Resolve organizer by slug OR id
        $organizerModel = \App\Models\Organizer::query()
            ->where('slug', $organizer)
            ->orWhere('id', $organizer)
            ->first();

        if (!$organizerModel) {
            return response()->json([
                'message' => 'Organizer not found',
                'organizer' => $organizer,
            ], 404);
        }

        $now = Carbon::now();

        // ✅ Base events query
        $eventsQuery = \App\Models\Event::query()
            ->where('organizer_id', $organizerModel->id);

        if (Schema::hasColumn('events', 'is_disabled')) {
            $eventsQuery->where('is_disabled', false);
        }

        // ✅ We have sessions table and session_date column
        // We'll compute next upcoming session_date per event, then sort by it
        $sub = DB::table('event_sessions')
            ->selectRaw('event_id, MIN(session_date) as next_date')
            ->where('session_date', '>=', $now)
            ->groupBy('event_id');

        $events = $eventsQuery
            ->joinSub($sub, 'ses', function ($join) {
                $join->on('events.id', '=', 'ses.event_id');
            })
            ->orderBy('ses.next_date')
            ->limit($limit)
            ->get([
                'events.*',
                DB::raw('ses.next_date as next_session_date'),
            ])
            ->map(function ($e) {
                $eventId = $e->id;

                // Pull the session_name for the next session date
                $nextSession = DB::table('event_sessions')
                    ->where('event_id', $eventId)
                    ->where('session_date', $e->next_session_date)
                    ->orderBy('session_date')
                    ->first();

                return [
                    'id' => (string) $e->id,
                    'public_id' => (string) ($e->public_id ?? $e->id),
                    'title' => (string) ($e->name ?? 'Event'),
                    'category' => (string) ($e->category ?? ''),
                    'location' => (string) ($e->location ?? ''),
                    'banner' => $e->banner_url ?: null,
                    'avatar' => $e->avatar_url ?: null,
                    'ticket_cost' => (float) ($e->ticket_cost ?? 0),
                    'ticket_currency' => (string) ($e->ticket_currency ?? ''),
                    'next_session_name' => (string) ($nextSession->session_name ?? ''),
                    'next_session_date' => $e->next_session_date
                        ? Carbon::parse($e->next_session_date)->toIso8601String()
                        : null,
                    'url' => (string) (config('app.url') . '/events/' . ($e->public_id ?? $e->id)),
                ];
            });

        return response()->json([
            'organizer' => [
                'id' => (string) $organizerModel->id,
                'slug' => (string) $organizerModel->slug,
                'name' => (string) $organizerModel->name,
            ],
            'count' => $events->count(),
            'events' => $events,
        ]);
    }
}
