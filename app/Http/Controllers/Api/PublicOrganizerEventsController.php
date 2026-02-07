<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PublicOrganizerEventsController extends Controller
{
    public function index(Request $request, string $slug)
    {
        $limit = (int) $request->query('limit', 6);

        // Cache to reduce load
        $cacheKey = "public:organizer-events:{$slug}:{$limit}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($slug, $limit) {

            $organizer = Organizer::where('slug', $slug)->firstOrFail();

            // ✅ Pull events for organizer (exclude disabled if exists)
            $events = Event::query()
                ->where('organizer_id', $organizer->id)
                ->when(Schema::hasColumn('events', 'is_disabled'), fn ($q) => $q->where('is_disabled', false))
                ->get()
                ->map(function ($e) {
                    // -----------------------------
                    // ✅ Banner normalization
                    // -----------------------------
                    $rawBanner = $e->banner_url; // full URL, /storage/..., or banners/... etc
                    $banner = null;

                    if ($rawBanner) {
                        if (Str::startsWith($rawBanner, ['http://', 'https://'])) {
                            $banner = $rawBanner;
                        } elseif (Str::startsWith($rawBanner, '/storage/')) {
                            $banner = URL::to($rawBanner);
                        } else {
                            $banner = URL::to('/storage/' . ltrim($rawBanner, '/'));
                        }
                    }

                    // -----------------------------
                    // ✅ Avatar normalization (optional)
                    // -----------------------------
                    $rawAvatar = $e->avatar_url;
                    $avatar = null;

                    if ($rawAvatar) {
                        if (Str::startsWith($rawAvatar, ['http://', 'https://'])) {
                            $avatar = $rawAvatar;
                        } elseif (Str::startsWith($rawAvatar, '/storage/')) {
                            $avatar = URL::to($rawAvatar);
                        } else {
                            $avatar = URL::to('/storage/' . ltrim($rawAvatar, '/'));
                        }
                    }

                    // -----------------------------
                    // ✅ Next upcoming session ONLY
                    // -----------------------------
                    $nextSession = null;

                    if (Schema::hasTable('event_sessions')) {
                        $today = Carbon::today()->toDateString();

                        $s = \DB::table('event_sessions')
                            ->where('event_id', $e->id)
                            ->where('session_date', '>=', $today) // ✅ only upcoming (today+)
                            ->orderBy('session_date', 'asc')
                            ->first();

                        if ($s) {
                            $nextSession = [
                                'name' => $s->session_name,
                                'date' => $s->session_date,
                                'timezone' => $s->timezone,
                            ];
                        }
                    }

                    return [
                        'id' => (string) ($e->public_id ?? $e->id),
                        'title' => (string) ($e->name ?? ''),
                        'category' => (string) ($e->category ?? ''),
                        'tags' => $e->tags ?? [],
                        'banner' => $banner,
                        'avatar' => $avatar,
                        'location' => (string) ($e->location ?? ''),
                        'next_session' => $nextSession, // ✅ null if no upcoming session
                        'organizer' => [
                            'id' => (int) $e->organizer_id,
                        ],
                    ];
                })
                // ✅ Keep ONLY events that actually have an upcoming session
                ->filter(fn ($e) => !empty($e['next_session']['date']))
                // ✅ Sort by next session date
                ->sortBy(fn ($e) => $e['next_session']['date'])
                // ✅ Limit after filtering/sorting
                ->take($limit)
                ->values();

            return response()->json([
                'organizer' => [
                    'id' => $organizer->id,
                    'name' => $organizer->name,
                    'slug' => $organizer->slug,
                    'avatar_url' => $organizer->avatar_url,
                ],
                'events' => $events,
            ]);
        });
    }


    /**
     * Convert a stored asset reference into a publicly accessible absolute URL.
     *
     * Accepts:
     * - https://...
     * - http://...
     * - /storage/...
     * - banners/xyz.jpg
     * - pages/home/xyz.jpg
     *
     * Returns:
     * - https://eventib.com/storage/...
     */
    private function normalizePublicAssetUrl(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $raw = trim($raw);

        // already absolute
        if (Str::startsWith($raw, ['http://', 'https://'])) {
            return $raw;
        }

        // already storage absolute path
        if (Str::startsWith($raw, '/storage/')) {
            return URL::to($raw);
        }

        // treat as public storage relative path
        return URL::to('/storage/' . ltrim($raw, '/'));
    }
}
