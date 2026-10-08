<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\MonitorResource;
use VentureDrake\LaravelCrm\Models\Monitor;
use VentureDrake\LaravelCrm\Services\MonitorService;

class MonitorController extends ApiController
{
    public function __construct(private MonitorService $monitorService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Monitor::class);

        $query = Monitor::query()->with(['ownerUser']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $query = $this->applySort(
            $query,
            $request,
            ['created_at', 'updated_at', 'last_checked_at', 'name'],
            '-created_at'
        );

        $monitors = $query->paginate($this->perPage($request))->withQueryString();

        return MonitorResource::collection($monitors);
    }

    public function show(Monitor $monitor)
    {
        $this->authorize('view', $monitor);

        $monitor->load(['ownerUser']);

        return new MonitorResource($monitor);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Monitor::class);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'url' => 'required|string|max:2048',
            'is_active' => 'nullable|boolean',
            'uptime_enabled' => 'nullable|boolean',
            'ssl_enabled' => 'nullable|boolean',
            'user_owner_id' => 'nullable|integer',
        ]);

        $monitor = $this->monitorService->create($validated);

        return (new MonitorResource($monitor))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, Monitor $monitor)
    {
        $this->authorize('update', $monitor);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'url' => 'sometimes|required|string|max:2048',
            'is_active' => 'nullable|boolean',
            'uptime_enabled' => 'nullable|boolean',
            'ssl_enabled' => 'nullable|boolean',
            'user_owner_id' => 'nullable|integer',
        ]);

        $monitor = $this->monitorService->update($monitor, $validated);

        return new MonitorResource($monitor);
    }

    public function destroy(Monitor $monitor)
    {
        $this->authorize('delete', $monitor);

        $monitor->delete();

        return response()->noContent();
    }
}
