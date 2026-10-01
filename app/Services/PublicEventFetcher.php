<?php
namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PublicEventFetcher
{
    public function get(string $url, int $maxBytes = 12582912): Response
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (!in_array($scheme, ['http','https'], true) || !$host || isset($parts['user']) || isset($parts['pass'])
            || !in_array($port, [80,443], true) || !preg_match('/^[a-z0-9.-]+$/', $host)) {
            throw new \InvalidArgumentException('Use a public HTTP or HTTPS URL.');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$addresses) throw new \InvalidArgumentException('Could not resolve this public website.');
        foreach ($addresses as $ip) {
            $numeric = (float)sprintf('%u', ip2long($ip));
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || ($numeric >= 1681915904 && $numeric <= 1686110207)) {
                throw new \InvalidArgumentException('Private and reserved network addresses cannot be imported.');
            }
        }
        if (!extension_loaded('curl')) throw new \RuntimeException('PHP cURL is required for secure URL imports.');
        // Pin the validated address; DNS cannot change the destination between validation and fetch.
        $response = Http::timeout(20)->withOptions([
            'allow_redirects' => false,
            'proxy' => '',
            'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.$addresses[0]], CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            'progress' => function ($total, $downloaded) use ($maxBytes) {
                if ($total > $maxBytes || $downloaded > $maxBytes) throw new \RuntimeException('Remote file exceeds the import size limit.');
            },
        ])->get($url);
        if ($response->redirect()) throw new \InvalidArgumentException('Use the final destination URL rather than a redirect link.');
        if (strlen($response->body()) > $maxBytes) throw new \RuntimeException('Remote file exceeds the import size limit.');
        return $response;
    }
}
