<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\PurchaseOrderResource;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\PurchaseOrder;
use VentureDrake\LaravelCrm\Services\PurchaseOrderService;

class PurchaseOrderController extends ApiController
{
    public function __construct(private PurchaseOrderService $purchaseOrderService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $query = PurchaseOrder::query()->with(['ownerUser', 'person', 'organization']);

        if ($request->filled('user_owner_id')) {
            $query->where('user_owner_id', $request->input('user_owner_id'));
        }

        $query = $this->applySort(
            $query,
            $request,
            ['created_at', 'updated_at', 'issue_date', 'delivery_date', 'total'],
            '-created_at'
        );

        $purchaseOrders = $query->paginate($this->perPage($request))->withQueryString();

        return PurchaseOrderResource::collection($purchaseOrders);
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load(['ownerUser', 'person', 'organization']);

        return new PurchaseOrderResource($purchaseOrder);
    }

    public function store(Request $request)
    {
        $this->authorize('create', PurchaseOrder::class);

        $validated = $request->validate([
            'reference' => 'nullable|string|max:255',
            'issue_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'currency' => 'nullable|string|max:3',
            'delivery_type' => 'nullable|string',
            'delivery_instructions' => 'nullable|string',
            'terms' => 'nullable|string',
            'person_id' => 'nullable|integer',
            'organization_id' => 'nullable|integer',
            'user_owner_id' => 'nullable|integer',
        ]);

        $person = ! empty($validated['person_id']) ? Person::find($validated['person_id']) : null;
        $organization = ! empty($validated['organization_id']) ? Organization::find($validated['organization_id']) : null;

        $purchaseOrder = $this->purchaseOrderService->create((object) $validated, $person, $organization);

        return (new PurchaseOrderResource($purchaseOrder))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('update', $purchaseOrder);

        $validated = $request->validate([
            'reference' => 'nullable|string|max:255',
            'issue_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'terms' => 'nullable|string',
            'user_owner_id' => 'nullable|integer',
        ]);

        $purchaseOrder = $this->purchaseOrderService->update((object) array_merge($purchaseOrder->toArray(), $validated), $purchaseOrder);

        return new PurchaseOrderResource($purchaseOrder);
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('delete', $purchaseOrder);

        $purchaseOrder->delete();

        return response()->noContent();
    }
}
