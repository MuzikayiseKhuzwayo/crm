<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\FeatureResource;
use VentureDrake\LaravelCrm\Models\Feature;
use VentureDrake\LaravelCrm\Services\FeatureService;

class FeatureController extends ApiController
{
    public function __construct(private FeatureService $featureService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Feature::class);

        $query = Feature::query()->with(['ownerUser', 'status']);

        if ($request->filled('user_owner_id')) {
            $query->where('user_owner_id', $request->input('user_owner_id'));
        }

        if ($request->filled('feature_status_id')) {
            $query->where('feature_status_id', $request->input('feature_status_id'));
        }

        $query = $this->applySort(
            $query,
            $request,
            ['created_at', 'updated_at', 'votes_count', 'title'],
            '-votes_count'
        );

        $features = $query->paginate($this->perPage($request))->withQueryString();

        return FeatureResource::collection($features);
    }

    public function show(Feature $feature)
    {
        $this->authorize('view', $feature);

        $feature->load(['ownerUser', 'status']);

        return new FeatureResource($feature);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Feature::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'nullable|boolean',
            'feature_status_id' => 'nullable|integer',
            'user_owner_id' => 'nullable|integer',
        ]);

        $feature = $this->featureService->create($validated, $request->user());

        return (new FeatureResource($feature))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, Feature $feature)
    {
        $this->authorize('update', $feature);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'nullable|boolean',
            'feature_status_id' => 'nullable|integer',
            'user_owner_id' => 'nullable|integer',
        ]);

        $feature = $this->featureService->update($feature, $validated);

        return new FeatureResource($feature);
    }

    public function destroy(Feature $feature)
    {
        $this->authorize('delete', $feature);

        $feature->delete();

        return response()->noContent();
    }
}
