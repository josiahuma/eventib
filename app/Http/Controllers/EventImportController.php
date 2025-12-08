<?php

namespace App\Http\Controllers;

use App\Models\Organizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EventImportController extends Controller
{
    public function form()
    {
        return view('events.import');
    }

    public function handle(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'url'],
        ]);

        try {
            $response = Http::timeout(20)->get($data['url']);
            $html     = $response->body();
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['url' => 'Could not fetch that URL.'])
                ->withInput();
        }

        $result = [
            'title'        => null,
            'description'  => null,
            'location'     => null,
            'start'        => null,
            'raw_category' => null,
            'image'        => null,
        ];

        if ($html) {
            // ---- 1) Try JSON-LD Event schema (this is your WORKING logic) ----
            if (preg_match_all(
                '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is',
                $html,
                $matches
            )) {
                foreach ($matches[1] as $json) {
                    $json = html_entity_decode($json, ENT_QUOTES | ENT_HTML5);

                    $decoded = json_decode($json, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        continue;
                    }

                    $candidates = [];

                    if (isset($decoded['@type'])) {
                        $candidates[] = $decoded;
                    } elseif (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
                        $candidates = array_merge($candidates, $decoded['@graph']);
                    } elseif (is_array($decoded)) {
                        $candidates = array_merge($candidates, $decoded);
                    }

                    foreach ($candidates as $node) {
                        if (!is_array($node)) {
                            continue;
                        }

                        $type = Arr::get($node, '@type');
                        if (is_array($type)) {
                            $type = $type[0] ?? null;
                        }

                        // ✅ accept MusicEvent, BusinessEvent, etc.
                        $typeStr = strtolower((string) $type);
                        if ($typeStr !== 'event' && !Str::endsWith($typeStr, 'event')) {
                            continue;
                        }

                        // Basic fields
                        $result['title']       = $result['title']       ?: Arr::get($node, 'name');
                        $result['description'] = $result['description'] ?: Arr::get($node, 'description');
                        $result['start']       = $result['start']       ?: Arr::get($node, 'startDate');

                        // Location can be an object or array of objects
                        $loc = Arr::get($node, 'location');
                        if ($loc && !$result['location']) {
                            if (isset($loc[0])) {
                                $loc = $loc[0]; // pick first
                            }

                            if (is_array($loc)) {
                                $name    = Arr::get($loc, 'name');
                                $address = Arr::get($loc, 'address');

                                $line = $name;

                                if (is_array($address)) {
                                    $parts = array_filter([
                                        Arr::get($address, 'streetAddress'),
                                        Arr::get($address, 'addressLocality'),
                                        Arr::get($address, 'postalCode'),
                                        Arr::get($address, 'addressCountry'),
                                    ]);
                                    if ($parts) {
                                        $line = trim(($line ? $line . ', ' : '') . implode(', ', $parts));
                                    }
                                } elseif (is_string($address)) {
                                    $line = trim(($line ? $line . ', ' : '') . $address);
                                }

                                $result['location'] = $line;
                            } elseif (is_string($loc)) {
                                $result['location'] = $loc;
                            }
                        }

                        // Category-ish fields from schema
                        $result['raw_category'] = $result['raw_category']
                            ?: Arr::get($node, 'eventCategory')
                            ?: Arr::get($node, 'eventType')
                            ?: Arr::get($node, 'category');

                        // Image (string or array)
                        if (!$result['image']) {
                            $image = Arr::get($node, 'image');
                            if (is_array($image)) {
                                $image = $image[0] ?? null;
                            }
                            if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
                                $result['image'] = $image;
                            }
                        }

                        // First matching Event is enough
                        break 2;
                    }
                }
            }

            // ---- 1b) Fallback: category from Eventbrite meta tag ----
            if (!$result['raw_category']) {
                if (preg_match(
                    '/<meta[^>]+(?:name|property)=["\'](?:eventbrite:category|eventbrite:event:category)["\'][^>]+content=["\']([^"\']+)["\']/i',
                    $html,
                    $m
                )) {
                    $result['raw_category'] = trim($m[1]);
                }
            }
            // ---- 2) Fallback: OpenGraph / Twitter image ----
            if (!$result['image']) {
                if (preg_match(
                    '#<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']#i',
                    $html,
                    $m
                )) {
                    $candidate = trim($m[1]);
                    if (filter_var($candidate, FILTER_VALIDATE_URL)) {
                        $result['image'] = $candidate;
                    }
                } elseif (preg_match(
                    '#<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']#i',
                    $html,
                    $m
                )) {
                    $candidate = trim($m[1]);
                    if (filter_var($candidate, FILTER_VALIDATE_URL)) {
                        $result['image'] = $candidate;
                    }
                }
            }
        }

        // ---- 3) Parse start date -> Session 1 ----
        $sessionPayload = [];
        if (!empty($result['start'])) {
            try {
                $dt = Carbon::parse($result['start']);
                $sessionPayload[] = [
                    'name' => $result['title'] ?? 'Main session',
                    'date' => $dt->toDateString(),
                    'time' => $dt->format('H:i'),
                ];
            } catch (\Throwable $e) {
                // ignore malformed dates
            }
        }

        // ---- 4) Map raw_category -> your internal categories ----
        $mappedCategory = null;

        // Build a big text blob we can scan for keywords.
        // If raw_category is missing, we fall back to title + description.
        $rawText = trim(
            ($result['raw_category'] ?? '') . ' ' .
            ($result['title'] ?? '') . ' ' .
            ($result['description'] ?? '')
        );

        if ($rawText !== '') {
            $raw = Str::of($rawText)->lower();

            if ($raw->contains('music') || $raw->contains('concert') || $raw->contains('gig')) {
                $mappedCategory = 'Music';
            } elseif (
                $raw->contains('business') ||
                $raw->contains('networking') ||
                $raw->contains('conference') ||
                $raw->contains('startup')
            ) {
                $mappedCategory = 'Business';
            } elseif ($raw->contains('tech') || $raw->contains('technology') || $raw->contains('developer')) {
                $mappedCategory = 'Technology';
            } elseif ($raw->contains('food') || $raw->contains('drink') || $raw->contains('dinner') || $raw->contains('brunch')) {
                $mappedCategory = 'Food & Drink';
            } elseif ($raw->contains('sport') || $raw->contains('football') || $raw->contains('basketball') || $raw->contains('race')) {
                $mappedCategory = 'Sports';
            } elseif (
                $raw->contains('religion') ||
                $raw->contains('church') ||
                $raw->contains('gospel') ||
                $raw->contains('worship')
            ) {
                $mappedCategory = 'Religion';
            } elseif (
                $raw->contains('community') ||
                $raw->contains('neighbourhood') ||
                $raw->contains('local')
            ) {
                $mappedCategory = 'Community';
            } elseif ($raw->contains('fashion') || $raw->contains('runway')) {
                $mappedCategory = 'Fashion';
            } elseif ($raw->contains('health') || $raw->contains('wellness') || $raw->contains('fitness') || $raw->contains('yoga')) {
                $mappedCategory = 'Health';
            } elseif (
                $raw->contains('education') ||
                $raw->contains('class') ||
                $raw->contains('course') ||
                $raw->contains('lecture') ||
                $raw->contains('workshop') ||
                $raw->contains('seminar') ||
                $raw->contains('training')
            ) {
                $mappedCategory = 'Education';
            } elseif ($raw->contains('travel') || $raw->contains('tour') || $raw->contains('trip')) {
                $mappedCategory = 'Travel';
            } elseif ($raw->contains('art') || $raw->contains('gallery') || $raw->contains('exhibition') || $raw->contains('theatre')) {
                $mappedCategory = 'Arts';
            } elseif ($raw->contains('entertain')) {
                $mappedCategory = 'Entertainment';
            } elseif (
                $raw->contains('charity') ||
                $raw->contains('fundraiser') ||
                $raw->contains('aid') ||
                $raw->contains('humanitarian') ||
                $raw->contains('relief')
            ) {
                // Your current event: “Overseas aid and humanitarian & disaster relief” will hit this.
                $mappedCategory = 'Charity';
            }
        }


        // ---- 5) Choose default organiser for this user (e.g. "unknown") ----
        $organizerId = null;
        if ($user = $request->user()) {
            // prefer organiser actually called "unknown" (case-insensitive)
            $unknown = Organizer::where('user_id', $user->id)
                ->whereRaw('LOWER(name) = ?', ['unknown'])
                ->first();

            if ($unknown) {
                $organizerId = $unknown->id;
            } else {
                // fallback: first organiser for this user (if any)
                $organizerId = Organizer::where('user_id', $user->id)
                    ->orderBy('id')
                    ->value('id');
            }
        }

        // ---- 6) Redirect to create form with prefilled fields ----
        return redirect()
            ->route('events.create')
            ->withInput([
                'name'                => $result['title']       ?: null,
                'description'         => $result['description'] ?: null,
                'location'            => $result['location']    ?: null,
                'category'            => $mappedCategory,
                'external_url'        => $data['url'],
                'sessions'            => $sessionPayload,
                'external_banner_url' => $result['image'],   // 👈 existing behaviour
                'organizer_id'        => $organizerId,       // 👈 auto-select organiser
            ]);
    }
}
