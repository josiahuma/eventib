<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class PublicOrganizerEventsController extends Controller
{
    public function index(Request $request, string $organizer)
    {
        // Simple API key check (so only FreshFountain can consume it)
        $key = (string) $request->query('key', '');
        if (!hash_equals((string) config('services.eventib_public_feed.key'), $key)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // IMPORTANT:
        // Adjust the query below to match YOUR Eventib schema.
        // I’m assuming you have:
        // - events table
        // - organizer_id column (or organizer_slug)
        // - banner_path or banner_url
        // - start_at/end_at (or sessions)
        // - venue/address
        // - categories for paid tickets (optional)
        //
        // If your Eventib stores sessions separately, you can swap this
        // to "next_session_at" logic.

        $now = Carbon::now();

        $events = \App\Models\Event::query()
            ->where(function ($q) use ($organizer) {
                // Option 1: organizer slug
                $q->where('organizer_slug', $organizer);

                // Option 2: organizer id (if you pass id in URL instead)
                // $q->orWhere('organizer_id', $organizer);
            })
            ->where('is_published', true)
            ->where('start_at', '>=', $now) // change if needed
            ->orderBy('start_at')
            ->limit((int) $request->query('limit', 8))
            ->get()
            ->map(function ($e) {
                $banner = $e->banner_url
                    ?? ($e->banner_path ? URL::to('/storage/' . ltrim($e->banner_path, '/')) : null);

                return [
                    'id' => (string) $e->id,
                    'title' => (string) $e->title,
                    'banner' => $banner,
                    'start_at' => optional($e->start_at)->toIso8601String(),
                    'end_at' => optional($e->end_at)->toIso8601String(),
                    'venue' => (string) ($e->venue ?? $e->location ?? ''),
                    'city' => (string) ($e->city ?? ''),
                    'url' => (string) (config('app.url') . '/events/' . $e->slug),
                    'badge' => $e->is_free ? 'Free' : 'Tickets',
                ];
            });

        return response()->json([
            'organizer' => $organizer,
            'count' => $events->count(),
            'events' => $events,
        ]);
    }
}
