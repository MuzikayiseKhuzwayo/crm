<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\DeliveryResource;
use VentureDrake\LaravelCrm\Models\Delivery;
use VentureDrake\LaravelCrm\Services\DeliveryService;

class DeliveryController extends ApiController
{
    public function __construct(private DeliveryService $deliveryService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Delivery::class);

        $query = Delivery::query()->with(['ownerUser', 'order']);

        if ($request->filled('user_owner_id')) {
            $query->where('user_owner_id', $request->input('user_owner_id'));
        }

        $query = $this->applySort(
            $query,
            $request,
            ['created_at', 'updated_at', 'delivery_expected', 'delivered_on'],
            '-created_at'
        );

        $deliveries = $query->paginate($this->perPage($request))->withQueryString();

        return DeliveryResource::collection($deliveries);
    }

    public function show(Delivery $delivery)
    {
        $this->authorize('view', $delivery);

        $delivery->load(['ownerUser', 'order']);

        return new DeliveryResource($delivery);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Delivery::class);

        $validated = $request->validate([
            'order_id' => 'required|integer|exists:'.config('laravel-crm.db_table_prefix').'orders,id',
            'delivery_expected' => 'nullable|date',
            'delivered_on' => 'nullable|date',
            'user_owner_id' => 'nullable|integer',
            'products' => 'nullable|array',
            'products.*.order_product_id' => 'required|integer',
            'products.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $delivery = $this->deliveryService->create((object) $validated);

        return (new DeliveryResource($delivery))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, Delivery $delivery)
    {
        $this->authorize('update', $delivery);

        $validated = $request->validate([
            'delivery_expected' => 'nullable|date',
            'delivered_on' => 'nullable|date',
            'user_owner_id' => 'nullable|integer',
        ]);

        $delivery = $this->deliveryService->update((object) array_merge($delivery->toArray(), $validated), $delivery);

        return new DeliveryResource($delivery);
    }

    public function destroy(Delivery $delivery)
    {
        $this->authorize('delete', $delivery);

        $delivery->delete();

        return response()->noContent();
    }
}
