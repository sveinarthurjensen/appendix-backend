<?php

namespace App\Functions;

use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Portert fra base44/functions/fetchImagesFromUrl/entry.ts
 *
 * Admin henter en nettside (f.eks. FINN-annonse) og får ut inntil 20 bilde-URLer.
 * SSRF-vern: kun http/https, ingen lokale/private adresser.
 * Svar: {success: true, images, count} eller {success: false, message}.
 */
class FetchImagesFromUrl extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $this->requireAdmin($user);

        $url = $payload['url'] ?? null;
        if (!$url) {
            throw new FunctionException('URL er påkrevd', 400, ['success' => false, 'message' => 'URL er påkrevd']);
        }

        try {
            $safeUrl = $this->assertSafeUrl($url);
        } catch (\RuntimeException $e) {
            throw new FunctionException($e->getMessage(), 400, ['success' => false, 'message' => $e->getMessage()]);
        }

        try {
            // Følger redirects som originalen (merk: redirect-målet SSRF-sjekkes ikke, heller ikke i Deno-versjonen)
            $response = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ])->get($safeUrl);
        } catch (\Throwable $e) {
            throw new FunctionException($e->getMessage(), 500, ['success' => false, 'message' => $e->getMessage()]);
        }

        if (!$response->successful()) {
            return ['success' => false, 'message' => 'Kunne ikke hente nettsiden'];
        }

        $html = $response->body();
        $images = [];
        $seen = [];

        $collect = function (string $pattern, int $group) use (&$images, &$seen, $html, $url) {
            if (preg_match_all($pattern, $html, $m)) {
                foreach ($m[$group] as $imgUrl) {
                    if ($this->isValidImageUrl($imgUrl) && !isset($seen[$imgUrl])) {
                        $seen[$imgUrl] = true;
                        $images[] = $this->normalizeUrl($imgUrl, $url);
                    }
                }
            }
        };

        // img src
        $collect('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', 1);
        // data-src (lazy loading)
        $collect('/data-src=["\']([^"\']+)["\']/i', 1);
        // bakgrunnsbilder i style
        $collect('/background(?:-image)?:\s*url\([\'"]?([^\'")\s]+)[\'"]?\)/i', 1);

        // FINN.no: høyoppløselige bilder legges først
        if (preg_match_all('#https://images\.finncdn\.no/dynamic/[^"\'\s]+#i', $html, $m)) {
            foreach ($m[0] as $imgUrl) {
                $hdUrl = preg_replace('#/\d+x\d+#', '/1600', $imgUrl, 1);
                if (!isset($seen[$hdUrl])) {
                    $seen[$hdUrl] = true;
                    array_unshift($images, $hdUrl);
                }
            }
        }

        $filtered = array_values(array_filter($images, function (string $img) {
            if (str_contains($img, 'icon') || str_contains($img, 'logo') || str_contains($img, 'sprite')) {
                return false;
            }
            if (str_contains($img, '1x1') || str_contains($img, 'pixel')) {
                return false;
            }
            if (str_contains($img, '.svg') || str_contains($img, '.gif')) {
                return false;
            }
            return true;
        }));
        $filtered = array_slice($filtered, 0, 20);

        return ['success' => true, 'images' => $filtered, 'count' => count($filtered)];
    }

    private function isValidImageUrl(?string $url): bool
    {
        if (!$url) {
            return false;
        }
        $hasExt = str_contains($url, '.jpg') || str_contains($url, '.jpeg') || str_contains($url, '.png') || str_contains($url, '.webp');
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '//')) {
            return $hasExt || str_contains($url, 'finncdn') || str_contains($url, 'images');
        }
        if (str_starts_with($url, '/') || str_starts_with($url, './')) {
            return $hasExt;
        }
        return false;
    }

    private function normalizeUrl(string $imgUrl, string $baseUrl): string
    {
        if (str_starts_with($imgUrl, '//')) {
            return 'https:' . $imgUrl;
        }
        if (str_starts_with($imgUrl, '/') || str_starts_with($imgUrl, './')) {
            $p = parse_url($baseUrl);
            $origin = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
            return $origin . (str_starts_with($imgUrl, './') ? substr($imgUrl, 1) : $imgUrl);
        }
        return $imgUrl;
    }

    /** Validerer URL for å forhindre SSRF (blokker private/loopback/metadata-adresser). */
    private function assertSafeUrl(string $raw): string
    {
        $u = parse_url($raw);
        if ($u === false || empty($u['scheme']) || !isset($u['host'])) {
            throw new \RuntimeException('Ugyldig URL');
        }
        $scheme = strtolower($u['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new \RuntimeException('Kun http- og https-URLer er tillatt');
        }
        $host = strtolower(trim($u['host'], '[]'));
        if ($host === '' || $host === 'localhost') {
            throw new \RuntimeException('Lokale adresser er ikke tillatt');
        }
        if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $host, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            if ($a === 10 || $a === 127 || $a === 0) {
                throw new \RuntimeException('Private/loopback-adresser er ikke tillatt');
            }
            if ($a === 169 && $b === 254) {
                throw new \RuntimeException('Link-local/metadata-adresser er ikke tillatt');
            }
            if ($a === 172 && $b >= 16 && $b <= 31) {
                throw new \RuntimeException('Private adresser er ikke tillatt');
            }
            if ($a === 192 && $b === 168) {
                throw new \RuntimeException('Private adresser er ikke tillatt');
            }
            if ($a === 100 && $b >= 64 && $b <= 127) {
                throw new \RuntimeException('CGNAT-område er ikke tillatt');
            }
        }
        if (str_contains($host, ':')) {
            if ($host === '::1' || $host === '::') {
                throw new \RuntimeException('IPv6 loopback er ikke tillatt');
            }
            if (str_starts_with($host, 'fe80')) {
                throw new \RuntimeException('Link-local IPv6 er ikke tillatt');
            }
            if (str_starts_with($host, 'fc') || str_starts_with($host, 'fd')) {
                throw new \RuntimeException('Unique-local IPv6 er ikke tillatt');
            }
        }
        return $raw;
    }
}
