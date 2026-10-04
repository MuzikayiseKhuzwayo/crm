<?php

namespace VentureDrake\LaravelCrm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Support\UuidNormalizer;

class NormalizeTelemetryBoundary
{
    /**
     * Handle incoming telemetry request and normalize UUID parameters to prevent 22P02 exceptions.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $idFields = ['entity_id', 'deal_id', 'organization_id', 'partner_id', 'user_id', 'external_id'];

        $input = $request->all();
        $modified = false;

        foreach ($idFields as $field) {
            if (isset($input[$field]) && is_string($input[$field]) && ! empty($input[$field])) {
                $input[$field] = UuidNormalizer::ensureValidUuid($input[$field]);
                $modified = true;
            }
        }

        if ($modified) {
            $request->merge($input);
        }

        return $next($request);
    }
}
