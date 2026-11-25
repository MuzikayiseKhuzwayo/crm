<?php

namespace VentureDrake\LaravelCrm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Safely attaches CORS headers for Chrome extension origins on crm/api/v2/*
 * without weakening Sanctum Bearer-token authentication or enabling cookies.
 */
class HandleExtensionCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if (! $this->isAllowedExtensionOrigin($origin)) {
            return $next($request);
        }

        if ($request->isMethod('OPTIONS')) {
            return $this->applyCorsHeaders(response('', 204), $origin);
        }

        $response = $next($request);

        return $this->applyCorsHeaders($response, $origin);
    }

    protected function isAllowedExtensionOrigin(?string $origin): bool
    {
        if (! is_string($origin) || ! config('laravel-crm.api.allow_chrome_extensions', true)) {
            return false;
        }

        if (! preg_match('/^chrome-extension:\/\/([a-z]{32})$/', $origin, $matches)) {
            return false;
        }

        $allowedIds = $this->configuredExtensionIds();

        if ($allowedIds === []) {
            return true;
        }

        return in_array($matches[1], $allowedIds, true);
    }

    /**
     * @return array<int, string>
     */
    protected function configuredExtensionIds(): array
    {
        $configured = config('laravel-crm.api.allowed_extension_ids', []);

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($id) => strtolower(trim((string) $id)),
            $configured
        )));
    }

    protected function applyCorsHeaders(Response $response, string $origin): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, X-Team-ID, X-Requested-With');
        $response->headers->set('Access-Control-Max-Age', '600');

        $existingVary = $response->headers->get('Vary');
        if ($existingVary === null || $existingVary === '') {
            $response->headers->set('Vary', 'Origin');
        } elseif (! str_contains(strtolower($existingVary), 'origin')) {
            $response->headers->set('Vary', $existingVary.', Origin');
        }

        return $response;
    }
}
