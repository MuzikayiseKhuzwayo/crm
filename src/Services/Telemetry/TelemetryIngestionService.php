<?php

namespace VentureDrake\LaravelCrm\Services\Telemetry;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\ProcessingPerformanceLog;
use VentureDrake\LaravelCrm\Models\TelemetryEvent;
use VentureDrake\LaravelCrm\Support\UuidNormalizer;

class TelemetryIngestionService
{
    /**
     * Ingest a telemetry event defensively.
     */
    public function ingest(array $payload, ?string $idempotencyKey = null): ?TelemetryEvent
    {
        $startTime = microtime(true);
        $traceId = UuidNormalizer::ensureValidUuid($payload['trace_id'] ?? null);

        // 1. Idempotency Check (x402 / key_hash style deduplication)
        $idempotencyHash = $idempotencyKey
            ? hash('sha256', $idempotencyKey)
            : hash('sha256', json_encode($payload));

        $cacheKey = 'crm_telemetry_idempotency:'.$idempotencyHash;

        if (Cache::has($cacheKey)) {
            $this->logPerformance($traceId, 'ingestion', (microtime(true) - $startTime) * 1000, 'idempotent_skip', [
                'idempotency_hash' => $idempotencyHash,
            ]);

            $existingId = Cache::get($cacheKey);

            return TelemetryEvent::where('external_id', $existingId)->first();
        }

        // 2. Defensive Boundary Normalization (22P02 Immunity)
        $entityId = UuidNormalizer::ensureValidUuid($payload['entity_id'] ?? null);
        $externalId = UuidNormalizer::ensureValidUuid($payload['external_id'] ?? null);
        $teamId = isset($payload['team_id']) && is_numeric($payload['team_id']) ? (int) $payload['team_id'] : null;

        // 3. Persist Telemetry Event
        $event = TelemetryEvent::create([
            'external_id' => $externalId,
            'team_id' => $teamId,
            'event_name' => (string) ($payload['event_name'] ?? 'custom_event'),
            'entity_type' => (string) ($payload['entity_type'] ?? 'deal'),
            'entity_id' => $entityId,
            'ansoff_quadrant' => (string) ($payload['ansoff_quadrant'] ?? 'market_penetration'),
            'pirate_stage' => (string) ($payload['pirate_stage'] ?? 'acquisition'),
            'metric_key' => (string) ($payload['metric_key'] ?? 'event_count'),
            'metric_value' => (float) ($payload['metric_value'] ?? 1.0),
            'payload' => $payload['payload'] ?? $payload,
            'recorded_at' => isset($payload['recorded_at']) ? $payload['recorded_at'] : now(),
        ]);

        // Cache idempotency key for 24 hours
        Cache::put($cacheKey, $externalId, now()->addHours(24));

        // 4. Log Performance Telemetry (Dubstrata SYS-001 Checkpoint)
        $durationMs = (microtime(true) - $startTime) * 1000;
        $this->logPerformance($traceId, 'db_write', $durationMs, 'success', [
            'event_id' => $event->id,
            'metric_key' => $event->metric_key,
        ]);

        return $event;
    }

    /**
     * Record processing performance latency.
     */
    public function logPerformance(string $traceId, string $stage, float $latencyMs, string $status, ?array $metadata = null): ProcessingPerformanceLog
    {
        return ProcessingPerformanceLog::create([
            'external_id' => (string) Str::uuid(),
            'trace_id' => $traceId,
            'stage' => $stage,
            'latency_ms' => round($latencyMs, 3),
            'status' => $status,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Query aggregate metrics by Ansoff quadrant and Pirate stage.
     */
    public function getMetricsSummary(string $ansoffQuadrant, ?string $pirateStage = null): array
    {
        $query = TelemetryEvent::where('ansoff_quadrant', $ansoffQuadrant);

        if ($pirateStage) {
            $query->where('pirate_stage', $pirateStage);
        }

        return [
            'quadrant' => $ansoffQuadrant,
            'stage' => $pirateStage,
            'event_count' => (int) $query->count(),
            'total_metric_value' => (float) $query->sum('metric_value'),
            'average_metric_value' => (float) ($query->avg('metric_value') ?? 0.0),
        ];
    }
}
