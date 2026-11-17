<?php

namespace App\Services;

use GuzzleHttp\Handler\CurlHandler;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OfficialDownload
{
    public const HOSTS = ['ojk.go.id', 'www.ojk.go.id', 'data.ojk.go.id', 'www.bi.go.id', 'bi.go.id', 'www.bps.go.id', 'bps.go.id', 'webapi.bps.go.id'];

    public function validateUrl(string $url, array $allowedHosts = self::HOSTS): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        parse_str($parts['query'] ?? '', $query);
        foreach (array_keys($query) as $key) {
            if (preg_match('/token|secret|password|api.?key|access.?key/i', $key)) {
                throw ValidationException::withMessages(['source_url' => 'URL berisi kredensial. Gunakan impor manual; jangan simpan secret dalam URL.']);
            }
        }
        if (! filter_var($url, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443) || ! in_array($host, array_intersect($allowedHosts, self::HOSTS), true)) {
            throw ValidationException::withMessages(['source_url' => 'Gunakan URL HTTPS dari domain sumber resmi yang diizinkan.']);
        }

        return $host;
    }

    public function fetch(string $url, array $allowedHosts): string
    {
        return $this->fetchWithMetadata($url, $allowedHosts)['content'];
    }

    /** @return array{content: string, url: string, last_modified: ?string, retrieved_at: string} */
    public function fetchWithMetadata(string $url, array $allowedHosts): array
    {
        for ($redirect = 0; $redirect < 4; $redirect++) {
            $host = $this->validateUrl($url, $allowedHosts);
            $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            $addresses = array_values(array_filter(array_map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null, $records)));
            if (! $addresses) {
                throw new \RuntimeException('DNS sumber tidak tersedia.');
            }
            foreach ($addresses as $address) {
                if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || str_starts_with(strtolower($address), '::ffff:')) {
                    throw new \RuntimeException('Alamat jaringan sumber tidak diizinkan.');
                }
            }
            $resolvedIp = str_contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];
            $response = Http::timeout(25)->connectTimeout(8)->withUserAgent('FINWATCH/1.0 official-public-data (+independent-research)')
                ->setHandler(new CurlHandler)->withOptions(['allow_redirects' => false, 'stream' => false, 'verify' => config('finwatch.official_ca_bundle') ?: true, 'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$resolvedIp]], 'progress' => function (float $total, float $downloaded): void {
                    if ($total > 10 * 1024 * 1024 || $downloaded > 10 * 1024 * 1024) {
                        throw new \RuntimeException('Berkas melampaui 10 MB.');
                    }
                }])->get($url);
            if ($response->redirect()) {
                $location = $response->header('Location');
                $url = str_starts_with($location, '/') && ! str_starts_with($location, '//') ? 'https://'.$host.$location : $location;

                continue;
            }
            $response->throw();
            if ((int) $response->header('Content-Length') > 10 * 1024 * 1024) {
                throw new \RuntimeException('Berkas melampaui 10 MB.');
            }
            $stream = $response->toPsrResponse()->getBody();
            $content = '';
            while (! $stream->eof()) {
                $content .= $stream->read(65536);
                if (strlen($content) > 10 * 1024 * 1024) {
                    throw new \RuntimeException('Berkas melampaui 10 MB.');
                }
            }

            $lastModified = null;
            if ($response->header('Last-Modified')) {
                try {
                    $lastModified = (new \DateTimeImmutable($response->header('Last-Modified')))->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM);
                } catch (\Exception) {
                    $lastModified = null;
                }
            }

            return ['content' => $content, 'url' => $url, 'last_modified' => $lastModified, 'retrieved_at' => now()->toIso8601String()];
        }
        throw new \RuntimeException('Terlalu banyak pengalihan sumber.');
    }
}
