<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\OrderProduct;

class OrderProductController extends Controller
{
    /*
     * Every method type-hints the parent, including the ones that only
     * abort(404), so `{order}` is resolved rather than left as a raw URL segment.
     *
     * Two reasons. A nonexistent parent now 404s at the binding rather than
     * reaching the method. And it puts $id back on the parameter it names:
     * with two route parameters and neither of them bound, route arguments
     * were passed positionally, so `show($id)` was handed the order's key
     * rather than the product's.
     */

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Order $order)
    {
        abort(404);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Order $order)
    {
        $orderProduct = $order->orderProducts()->create([
            'external_id' => Uuid::uuid4()->toString(),
            'currency' => $order->currency,
        ]);

        return view('laravel-crm::order-products.create', [
            'orderProduct' => $orderProduct,
            'index' => $order->orderProducts->count() - 1,
        ]);
    }

    public function createProduct()
    {
        return view('laravel-crm::order-products.create-product', [
            'index' => rand(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request, Order $order)
    {
        abort(404);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show(Order $order, $id)
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(Order $order, OrderProduct $product)
    {
        return view('laravel-crm::order-products.edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, Order $order, $id)
    {
        abort(404);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Order $order, $id)
    {
        abort(404);
    }
}
