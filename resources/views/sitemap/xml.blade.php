<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $u)
    <url>
        <loc>{{ htmlspecialchars($u['loc'], ENT_XML1) }}</loc>
        @isset($u['lastmod'])
            <lastmod>{{ $u['lastmod'] }}</lastmod>
        @endisset
        @isset($u['freq'])
            <changefreq>{{ $u['freq'] }}</changefreq>
        @endisset
        @isset($u['priority'])
            <priority>{{ $u['priority'] }}</priority>
        @endisset
    </url>
@endforeach
</urlset>
