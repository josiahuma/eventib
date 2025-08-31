<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        // Static pages
        $urls = [
            [
                'loc'      => route('homepage'),
                'priority' => '1.0',
                'freq'     => 'daily',
            ],
            [
                'loc'      => route('how'),
                'priority' => '0.3',
                'freq'     => 'monthly',
            ],
            [
                'loc'      => route('pricing'),
                'priority' => '0.5',
                'freq'     => 'monthly',
            ],
        ];

        // Public events (adjust scope/conditions to match your app)
        $events = Event::query()
            ->select(['id', 'public_id', 'updated_at'])
            ->where('is_published', true)
            ->orderBy('id')
            ->get();

        foreach ($events as $e) {
            $urls[] = [
                'loc'      => route('events.show', $e), // relies on implicit binding via public_id
                'lastmod'  => optional($e->updated_at)->tz('UTC')->toAtomString(),
                'priority' => '0.8',
                'freq'     => 'weekly',
            ];
        }

        $xml = view('sitemap.xml', compact('urls'))->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
