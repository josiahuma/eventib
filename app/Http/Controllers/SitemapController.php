<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Support\Facades\URL;

class SitemapController extends Controller
{
    public function index()
    {
        $urls = [];

        // Static pages
        $urls[] = [
            'loc'        => route('homepage'),
            'lastmod'    => now()->toAtomString(),
            'changefreq' => 'daily',
            'priority'   => '1.0',
        ];
        if (route('how', absolute: false)) {
            $urls[] = [
                'loc'        => route('how'),
                'lastmod'    => now()->toAtomString(),
                'changefreq' => 'monthly',
                'priority'   => '0.3',
            ];
        }
        if (route('pricing', absolute: false)) {
            $urls[] = [
                'loc'        => route('pricing'),
                'lastmod'    => now()->toAtomString(),
                'changefreq' => 'monthly',
                'priority'   => '0.3',
            ];
        }

        // Event pages (use implicit binding to public_id)
        Event::query()
            ->select(['id', 'public_id', 'updated_at'])
            ->orderByDesc('updated_at')
            ->chunk(300, function ($chunk) use (&$urls) {
                foreach ($chunk as $event) {
                    $urls[] = [
                        'loc'        => route('events.show', $event),
                        'lastmod'    => optional($event->updated_at)->toAtomString() ?? now()->toAtomString(),
                        'changefreq' => 'daily',
                        'priority'   => '0.7',
                    ];
                }
            });

        // Build XML
        $xmlItems = array_map(function ($u) {
            return
                "  <url>\n" .
                "    <loc>" . e($u['loc']) . "</loc>\n" .
                "    <lastmod>{$u['lastmod']}</lastmod>\n" .
                "    <changefreq>{$u['changefreq']}</changefreq>\n" .
                "    <priority>{$u['priority']}</priority>\n" .
                "  </url>";
        }, $urls);

        $xml =
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
            "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n" .
            implode("\n", $xmlItems) . "\n" .
            "</urlset>\n";

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
