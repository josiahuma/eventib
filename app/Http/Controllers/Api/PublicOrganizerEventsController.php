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
        // ✅ Simple API key check (so only FreshFountain can consume it)
        $key = (string) $request->query('key', '');
        if (!hash_equals((string) config('services.eventib_public_feed.key'), $key)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $limit = (int) $request->query('limit', 8);
        $limit = $limit > 0 ? min($limit, 30) : 8;

        // ✅ Resolve organizer from slug OR id
        // Assumes you have an Organizer model + table.
        // If your model name differs, adjust here.
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

        // ✅ Pull events for this organizer_id
        // NOTE: If you don't have start_at/end_at on events because you store sessions separately,
        // scroll down to the "If you use sessions" note after this file.
        $events = \App\Models\Event::query()
            ->where('organizer_id', $organizerModel->id)
            ->where('is_published', true)
            ->where(function ($q) use ($now) {
                // Supports either start_at or starts_at naming.
                if (\Schema::hasColumn('events', 'start_at')) {
                    $q->where('start_at', '>=', $now);
                } elseif (\Schema::hasColumn('events', 'starts_at')) {
                    $q->where('starts_at', '>=', $now);
                } else {
                    // If no date column exists on events, we cannot filter here.
                    // We'll just return latest published (still useful).
                }
            })
            ->when(\Schema::hasColumn('events', 'start_at'), fn ($q) => $q->orderBy('start_at'))
            ->when(!\Schema::hasColumn('events', 'start_at') && \Schema::hasColumn('events', 'starts_at'), fn ($q) => $q->orderBy('starts_at'))
            ->limit($limit)
            ->get()
            ->map(function ($e) {
                // ✅ Banner mapping
                // Adjust these fields to match YOUR schema:
                // - banner_url (string url) OR
                // - banner_path (storage path)
                // - banner (some people name it just "banner")
                $bannerPath = $e->banner_path ?? $e->banner ?? null;

                $banner = $e->banner_url
                    ?? ($bannerPath ? URL::to('/storage/' . ltrim($bannerPath, '/')) : null);

                // ✅ Date mapping
                $startAt = null;
                $endAt = null;

                if (isset($e->start_at)) $startAt = optional($e->start_at)->toIso8601String();
                if (isset($e->starts_at)) $startAt = $startAt ?: optional($e->starts_at)->toIso8601String();

                if (isset($e->end_at)) $endAt = optional($e->end_at)->toIso8601String();
                if (isset($e->ends_at)) $endAt = $endAt ?: optional($e->ends_at)->toIso8601String();

                // ✅ URL mapping
                // If you use slug, keep slug. Otherwise fall back to id.
                $slugOrId = $e->slug ?? $e->id;

                // ✅ Badge
                // If you don't have is_free column, it will just be null.
                $badge = null;
                if (isset($e->is_free)) {
                    $badge = $e->is_free ? 'Free' : 'Tickets';
                }

                return [
                    'id' => (string) $e->id,
                    'title' => (string) ($e->title ?? 'Event'),
                    'banner' => $banner,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'venue' => (string) ($e->venue ?? $e->location ?? ''),
                    'city' => (string) ($e->city ?? ''),
                    'url' => (string) (config('app.url') . '/events/' . $slugOrId),
                    'badge' => $badge,
                ];
            });

        return response()->json([
            'organizer' => [
                'id' => (string) $organizerModel->id,
                'slug' => (string) ($organizerModel->slug ?? ''),
                'name' => (string) ($organizerModel->name ?? ''),
            ],
            'count' => $events->count(),
            'events' => $events,
        ]);
    }
}
