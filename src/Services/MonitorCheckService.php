<?php

namespace VentureDrake\LaravelCrm\Services;

use Carbon\Carbon;
use GuzzleHttp\Exception\TooManyRedirectsException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;
use VentureDrake\LaravelCrm\Models\Monitor;

class MonitorCheckService
{
    public function checkUptime(Monitor $monitor): array
    {
        $result = [
            'status' => 'down',
            'status_code' => null,
            'response_time_ms' => null,
            'error' => null,
        ];

        $timeout = (int) config('laravel-crm.monitoring.request_timeout_seconds', 15);
        $maxBytes = (int) config('laravel-crm.monitoring.max_response_bytes', 5 * 1024 * 1024);

        $start = microtime(true);

        if ($reason = MonitorUrlGuard::reasonForRejection($monitor->url)) {
            $result['error'] = $reason;

            return $result;
        }

        $host = parse_url($monitor->url, PHP_URL_HOST);
        $allowPrivate = (bool) config('laravel-crm.monitoring.allow_private_targets');
        $resolved = ($host && ! $allowPrivate) ? MonitorUrlGuard::resolvePublicIps($host) : [];

        if (! $allowPrivate && $resolved === []) {
            $result['error'] = 'URL host could not be safely resolved.';

            return $result;
        }

        try {
            $response = Http::timeout($timeout)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 5,
                        'strict' => false,
                        'referer' => false,
                        'protocols' => ['http', 'https'],
                        'track_redirects' => false,
                        'on_redirect' => static function ($request, $response, UriInterface $uri): void {
                            if (MonitorUrlGuard::reasonForRejection((string) $uri) !== null) {
                                throw new RuntimeException('Redirect to non-public host blocked.');
                            }
                        },
                    ],
                    'curl' => $resolved !== []
                        ? [CURLOPT_RESOLVE => self::buildCurlResolve($host, $resolved)]
                        : [],
                ])
                ->withHeaders(['Accept-Encoding' => 'identity'])
                ->sink(fopen('php://temp', 'r+'))
                ->get($monitor->url);

            $contentLength = (int) ($response->header('Content-Length') ?: 0);

            if ($contentLength > $maxBytes) {
                $result['response_time_ms'] = (int) round((microtime(true) - $start) * 1000);
                $result['status_code'] = $response->status();
                $result['error'] = 'Response exceeds maximum allowed size.';
                $result['status'] = 'down';

                return $result;
            }

            $result['response_time_ms'] = (int) round((microtime(true) - $start) * 1000);
            $result['status_code'] = $response->status();

            if ($response->successful()) {
                $threshold = $monitor->perf_threshold_ms ?? null;

                if ($threshold !== null && $result['response_time_ms'] > (int) $threshold) {
                    $result['status'] = 'slow';
                } else {
                    $result['status'] = 'up';
                }
            } else {
                $result['status'] = 'down';
                $result['error'] = 'HTTP '.$response->status();
            }
        } catch (TooManyRedirectsException $e) {
            $result['response_time_ms'] = (int) round((microtime(true) - $start) * 1000);
            $result['error'] = 'Too many redirects (or redirect blocked): '.$e->getMessage();
        } catch (Throwable $e) {
            $result['response_time_ms'] = (int) round((microtime(true) - $start) * 1000);
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    public function checkSsl(Monitor $monitor): array
    {
        $result = [
            'valid' => false,
            'issuer' => null,
            'expires_at' => null,
            'error' => null,
        ];

        $host = $monitor->host ?: parse_url($monitor->url, PHP_URL_HOST);

        if (! $host) {
            $result['error'] = 'No host available for SSL check';

            return $result;
        }

        if ($reason = MonitorUrlGuard::reasonForRejection('https://'.$host)) {
            $result['error'] = $reason;

            return $result;
        }

        $allowPrivate = (bool) config('laravel-crm.monitoring.allow_private_targets');
        $resolved = $allowPrivate ? [] : MonitorUrlGuard::resolvePublicIps($host);

        if (! $allowPrivate && $resolved === []) {
            $result['error'] = 'SSL host could not be safely resolved.';

            return $result;
        }

        $timeout = (int) config('laravel-crm.monitoring.request_timeout_seconds', 15);

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $errno = 0;
        $errstr = '';

        $connectIp = $resolved[0] ?? $host;
        $connectHost = filter_var($connectIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? '['.$connectIp.']'
            : $connectIp;

        $client = @stream_socket_client(
            'ssl://'.$connectHost.':443',
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($client === false) {
            $result['error'] = $errstr !== '' ? $errstr : 'Unable to connect to '.$host.':443';

            return $result;
        }

        try {
            $params = stream_context_get_params($client);
            $cert = $params['options']['ssl']['peer_certificate'] ?? null;

            if (! $cert) {
                $result['error'] = 'No peer certificate captured';

                return $result;
            }

            $parsed = openssl_x509_parse($cert);

            if (! $parsed) {
                $result['error'] = 'Unable to parse certificate';

                return $result;
            }

            $issuerParts = $parsed['issuer'] ?? [];
            $result['issuer'] = $issuerParts['CN']
                ?? $issuerParts['O']
                ?? (is_array($issuerParts) ? implode(', ', array_map(
                    fn ($k, $v) => $k.'='.(is_array($v) ? implode('/', $v) : $v),
                    array_keys($issuerParts),
                    array_values($issuerParts)
                )) : null);

            if (isset($parsed['validTo_time_t'])) {
                $result['expires_at'] = Carbon::createFromTimestamp($parsed['validTo_time_t']);
            }

            if ($result['expires_at'] !== null && $result['expires_at']->isPast()) {
                $result['error'] = 'Certificate expired on '.$result['expires_at']->toIso8601String();
                $result['valid'] = false;

                return $result;
            }

            $result['valid'] = true;
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            $result['valid'] = false;
        } finally {
            if (is_resource($client)) {
                fclose($client);
            }
        }

        return $result;
    }

    /**
     * Build CURLOPT_RESOLVE entries so the actual HTTP call uses the IP the
     * guard already approved — this closes the DNS-rebinding window between
     * MonitorUrlGuard::resolvePublicIps() and the outbound request.
     *
     * @param  array<int, string>  $ips
     * @return array<int, string>
     */
    private static function buildCurlResolve(?string $host, array $ips): array
    {
        if (! $host || $ips === []) {
            return [];
        }

        $entries = [];
        $primary = $ips[0];

        foreach ([80, 443] as $port) {
            $entries[] = sprintf('%s:%d:%s', $host, $port, $primary);
        }

        return $entries;
    }
}
