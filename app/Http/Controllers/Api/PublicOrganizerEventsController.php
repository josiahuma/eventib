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
        $limit = $limit > 0 ? min($limit, 24) : 6;

        // Cache to reduce load
        $cacheKey = "public:organizer-events:{$slug}:{$limit}";

        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($slug, $limit) {

            $organizer = Organizer::query()
                ->where('slug', $slug)
                ->firstOrFail();

            $events = Event::query()
                ->where('organizer_id', $organizer->id)
                ->when(Schema::hasColumn('events', 'is_disabled'), fn ($q) => $q->where('is_disabled', false))
                ->limit($limit)
                ->get()
                ->map(function ($e) use ($organizer) {

                    // -----------------------------
                    // ✅ Banner normalization
                    // banner_url can be:
                    // - full URL
                    // - /storage/...
                    // - banners/xxx.jpg  (storage-relative)
                    // -----------------------------
                    $banner = $this->normalizePublicAssetUrl($e->banner_url ?? null);

                    // -----------------------------
                    // ✅ Avatar normalization (optional)
                    // -----------------------------
                    $avatar = $this->normalizePublicAssetUrl($e->avatar_url ?? null);

                    // -----------------------------
                    // ✅ Next upcoming session (if event_sessions exists)
                    // -----------------------------
                    $nextSession = null;

                    if (Schema::hasTable('event_sessions')) {
                        $nowDate = Carbon::now()->toDateString(); // session_date is DATE column

                        $s = \DB::table('event_sessions')
                            ->where('event_id', $e->id)
                            ->where('session_date', '>=', $nowDate)
                            ->orderBy('session_date', 'asc')
                            ->first();

                        // If none upcoming, fallback to earliest (optional)
                        if (! $s) {
                            $s = \DB::table('event_sessions')
                                ->where('event_id', $e->id)
                                ->orderBy('session_date', 'asc')
                                ->first();
                        }

                        if ($s) {
                            $nextSession = [
                                'name' => (string) ($s->session_name ?? ''),
                                'date' => (string) ($s->session_date ?? ''),
                                'timezone' => (string) ($s->timezone ?? ''),
                            ];
                        }
                    }

                    // -----------------------------
                    // ✅ Tags normalization
                    // tags is usually json/cast array, but make it safe
                    // -----------------------------
                    $tags = $e->tags ?? [];
                    if (is_string($tags)) {
                        $decoded = json_decode($tags, true);
                        $tags = is_array($decoded) ? $decoded : [];
                    }
                    if (! is_array($tags)) {
                        $tags = [];
                    }

                    // ✅ Fix precedence: public_id fallback
                    $publicId = $e->public_id ?? $e->id;

                    return [
                        'id' => (string) $publicId,
                        'title' => (string) ($e->name ?? ''),
                        'category' => (string) ($e->category ?? ''),
                        'tags' => $tags,
                        'banner' => $banner,
                        'avatar' => $avatar,
                        'location' => (string) ($e->location ?? ''),
                        'next_session' => $nextSession,
                        'organizer' => [
                            'id' => (string) $organizer->id,
                            'name' => (string) $organizer->name,
                            'slug' => (string) $organizer->slug,
                            'avatar_url' => $this->normalizePublicAssetUrl($organizer->avatar_url ?? null),
                            'website' => (string) ($organizer->website ?? ''),
                        ],
                        // Optional: direct Eventib link if you want to click out
                        'url' => URL::to('/events/' . (string) $publicId),
                    ];
                })
                ->values()
                ->all();

            return [
                'organizer' => [
                    'id' => (string) $organizer->id,
                    'name' => (string) $organizer->name,
                    'slug' => (string) $organizer->slug,
                    'avatar_url' => $this->normalizePublicAssetUrl($organizer->avatar_url ?? null),
                    'website' => (string) ($organizer->website ?? ''),
                ],
                'events' => $events,
            ];
        });

        return response()->json($payload);
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
