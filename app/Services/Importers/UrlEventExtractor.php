<?php

// app/Services/Importers/UrlEventExtractor.php
namespace App\Services\Importers;

use Illuminate\Support\Facades\Http;

class UrlEventExtractor
{
    public function extract(string $url): ?array
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';

        // 1) If it's an Eventbrite event URL, try their API using the ID
        if (str_contains($host, 'eventbrite.')) {
            return $this->fromEventbriteUrl($url);
        }

        // 2) If it's Ticketmaster, you *could* parse the ID & hit discovery API
        if (str_contains($host, 'ticketmaster.')) {
            // TODO: optional – similar to Eventbrite
        }

        // 3) Fallback: generic HTML meta tag scraping
        return $this->fromMetaTags($url);
    }

    protected function fromEventbriteUrl(string $url): ?array
    {
        // Event URLs usually look like /e/name-of-event-1234567890
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if (! preg_match('/-(\d+)\/?$/', $path, $m)) {
            return null;
        }

        $id = $m[1];

        $res = Http::withToken(config('services.eventbrite.token'))
            ->get("https://www.eventbriteapi.com/v3/events/{$id}/");

        if (! $res->successful()) return null;

        $ev = $res->json();

        return [
            'title'       => $ev['name']['text'] ?? null,
            'description' => $ev['description']['html'] ?? $ev['description']['text'] ?? null,
            'location'    => null, // would require a separate venue call
        ];
    }

    protected function fromMetaTags(string $url): ?array
    {
        $res = Http::get($url);
        if (! $res->successful()) {
            return null;
        }

        $html = $res->body();

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML($html);
        $xpath = new \DOMXPath($doc);

        $meta = fn(string $name) => $this->firstMeta($xpath, $name);

        $title = $meta('og:title')
            ?? $meta('twitter:title')
            ?? $this->firstNodeText($xpath, '//title');

        $description = $meta('og:description')
            ?? $meta('twitter:description')
            ?? $this->firstMeta($xpath, 'description');

        // Some platforms put venue / city in custom tags – hard to generalise
        // You can extend this later with site-specific tweaks.
        $location = null;

        if (! $title && ! $description) return null;

        return [
            'title'       => $title,
            'description' => $description,
            'location'    => $location,
        ];
    }

    protected function firstMeta(\DOMXPath $xpath, string $name): ?string
    {
        $queries = [
            "//meta[@property='{$name}']/@content",
            "//meta[@name='{$name}']/@content",
        ];

        foreach ($queries as $q) {
            $nodes = $xpath->query($q);
            if ($nodes && $nodes->length > 0) {
                return trim($nodes->item(0)->nodeValue);
            }
        }

        return null;
    }

    protected function firstNodeText(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);
        if ($nodes && $nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }
        return null;
    }
}
