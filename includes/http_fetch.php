<?php
declare(strict_types=1);

/**
 * SSRF-guarded outbound HTTP fetching, used by both the metadata fetch on
 * link save and the cron dead-link checker. Rules:
 *  - http/https only
 *  - hostname is DNS-resolved and every resolved IP is checked against
 *    private/loopback/link-local ranges before any request is made
 *  - the request is then pinned to the vetted IP (CURLOPT_RESOLVE), so a
 *    DNS answer that changes between check and connect can't be abused
 *  - redirects are followed manually (max 3 hops), re-validating the
 *    target host on every hop
 *  - response body is capped and timeouts are kept short, so a slow or
 *    huge response can't hold up the request within shared-hosting limits
 */

const WF_MAX_REDIRECTS = 3;
const WF_CONNECT_TIMEOUT = 3;
const WF_TOTAL_TIMEOUT = 5;
const WF_MAX_BYTES = 1_000_000;

function wf_is_public_ip(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false;
    }
    // Belt-and-braces: explicit link-local v6 check regardless of platform
    // filter_var behaviour.
    if (stripos($ip, 'fe80:') === 0) {
        return false;
    }
    return true;
}

/**
 * Resolves a hostname to its public IPs, or returns false if it's an IP
 * literal, or resolves to (any) private/loopback/link-local address.
 */
function wf_resolve_public_ips(string $host): array|false
{
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return wf_is_public_ip($host) ? [$host] : false;
    }

    $ips = [];
    foreach (@dns_get_record($host, DNS_A) ?: [] as $record) {
        if (!empty($record['ip'])) {
            $ips[] = $record['ip'];
        }
    }
    foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
        if (!empty($record['ipv6'])) {
            $ips[] = $record['ipv6'];
        }
    }
    if (empty($ips)) {
        $ip = @gethostbyname($host);
        if ($ip !== null && $ip !== $host) {
            $ips[] = $ip;
        }
    }
    if (empty($ips)) {
        return false;
    }

    foreach ($ips as $ip) {
        if (!wf_is_public_ip($ip)) {
            return false;
        }
    }

    return $ips;
}

function wf_resolve_relative_url(string $base, string $location): string
{
    if (preg_match('#^https?://#i', $location)) {
        return $location;
    }
    $baseParts = parse_url($base);
    $scheme = $baseParts['scheme'] ?? 'http';
    $host = $baseParts['host'] ?? '';
    $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';

    if (str_starts_with($location, '//')) {
        return $scheme . ':' . $location;
    }
    if (str_starts_with($location, '/')) {
        return $scheme . '://' . $host . $port . $location;
    }
    $basePath = $baseParts['path'] ?? '/';
    $dir = substr($basePath, 0, (int) strrpos($basePath, '/') + 1);
    return $scheme . '://' . $host . $port . $dir . $location;
}

/**
 * Performs one SSRF-guarded fetch, following up to WF_MAX_REDIRECTS
 * redirects manually. Returns null on any failure (bad scheme, private
 * IP, timeout, oversized response, curl error).
 */
function wf_safe_fetch(string $url, string $method = 'GET'): ?array
{
    for ($hop = 0; $hop <= WF_MAX_REDIRECTS; $hop++) {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || empty($parts['scheme'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $ips = wf_resolve_public_ips($parts['host']);
        if ($ips === false) {
            return null;
        }
        $ip = $ips[0];
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        $bytesRead = 0;
        $body = '';
        $responseHeaders = [];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY          => $method === 'HEAD',
            CURLOPT_FOLLOWLOCATION  => false,
            CURLOPT_CONNECTTIMEOUT  => WF_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT         => WF_TOTAL_TIMEOUT,
            CURLOPT_RESOLVE         => [sprintf('%s:%d:%s', $parts['host'], $port, $ip)],
            CURLOPT_PROTOCOLS       => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER  => true,
            CURLOPT_SSL_VERIFYHOST  => 2,
            CURLOPT_USERAGENT       => 'Waymark/1.0 (+link metadata fetcher)',
            CURLOPT_WRITEFUNCTION   => function ($handle, $chunk) use (&$bytesRead, &$body) {
                $bytesRead += strlen($chunk);
                if ($bytesRead > WF_MAX_BYTES) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION  => function ($handle, $headerLine) use (&$responseHeaders) {
                $responseHeaders[] = $headerLine;
                return strlen($headerLine);
            },
        ]);

        curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // CURLE_WRITE_ERROR (23) is expected when our size cap aborts the
        // transfer - treat whatever we captured up to the cap as valid.
        if ($errno !== 0 && $errno !== CURLE_WRITE_ERROR) {
            return null;
        }

        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            $location = null;
            foreach ($responseHeaders as $line) {
                if (preg_match('/^location:\s*(.+)$/i', trim($line), $m)) {
                    $location = trim($m[1]);
                }
            }
            if (!$location) {
                return null;
            }
            $url = wf_resolve_relative_url($url, $location);
            continue;
        }

        $contentType = '';
        foreach ($responseHeaders as $line) {
            if (preg_match('/^content-type:\s*(.+)$/i', trim($line), $m)) {
                $contentType = trim($m[1]);
            }
        }

        return [
            'status'       => $status,
            'body'         => $body,
            'content_type' => $contentType,
            'final_url'    => $url,
        ];
    }

    return null;
}

/**
 * Fetches a page and extracts <title>, meta description and og:image.
 * Best-effort and side-effect free on failure: always returns the three
 * keys, empty when unavailable, and never throws.
 */
function wf_fetch_metadata(string $url): array
{
    $result = ['title' => '', 'description' => '', 'image_url' => ''];

    $response = wf_safe_fetch($url, 'GET');
    if (!$response || $response['status'] < 200 || $response['status'] >= 300) {
        return $result;
    }
    if ($response['content_type'] !== '' && stripos($response['content_type'], 'html') === false) {
        return $result;
    }
    if ($response['body'] === '') {
        return $result;
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $response['body']);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $titleNode = $xpath->query('//title')->item(0);
    if ($titleNode) {
        $result['title'] = trim($titleNode->textContent);
    }

    foreach ($xpath->query('//meta') as $meta) {
        /** @var DOMElement $meta */
        $name = strtolower($meta->getAttribute('name'));
        $property = strtolower($meta->getAttribute('property'));
        $content = trim($meta->getAttribute('content'));

        if ($content === '') {
            continue;
        }
        if ($name === 'description' && $result['description'] === '') {
            $result['description'] = $content;
        }
        if ($property === 'og:description' && $result['description'] === '') {
            $result['description'] = $content;
        }
        if ($property === 'og:title' && $result['title'] === '') {
            $result['title'] = $content;
        }
        if ($property === 'og:image' && $result['image_url'] === '') {
            $result['image_url'] = $content;
        }
    }

    if ($result['image_url'] !== '') {
        $resolved = wf_resolve_relative_url($response['final_url'], $result['image_url']);
        $imgScheme = strtolower((string) parse_url($resolved, PHP_URL_SCHEME));
        $result['image_url'] = in_array($imgScheme, ['http', 'https'], true) ? $resolved : '';
    }

    $result['title'] = mb_substr($result['title'], 0, 500);
    $result['description'] = mb_substr($result['description'], 0, 2000);
    $result['image_url'] = mb_substr($result['image_url'], 0, 2000);

    return $result;
}
