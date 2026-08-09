<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\QuoteProduct;

class QuoteProductController extends Controller
{
    /*
     * Every method type-hints the parent, including the ones that only
     * abort(404), so `{quote}` is resolved rather than left as a raw URL segment.
     *
     * Two reasons. A nonexistent parent now 404s at the binding rather than
     * reaching the method. And it puts $id back on the parameter it names:
     * with two route parameters and neither of them bound, route arguments
     * were passed positionally, so `show($id)` was handed the quote's key
     * rather than the product's.
     */

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Quote $quote)
    {
        abort(404);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Quote $quote)
    {
        $quoteProduct = $quote->quoteProducts()->create([
            'external_id' => Uuid::uuid4()->toString(),
            'currency' => $quote->currency,
        ]);

        return view('laravel-crm::quote-products.create', [
            'quoteProduct' => $quoteProduct,
            'index' => $quote->quoteProducts->count() - 1,
        ]);
    }

    public function createProduct()
    {
        return view('laravel-crm::quote-products.create-product', [
            'index' => rand(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request, Quote $quote)
    {
        abort(404);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show(Quote $quote, $id)
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(Quote $quote, QuoteProduct $product)
    {
        return view('laravel-crm::quote-products.edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, Quote $quote, $id)
    {
        abort(404);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Quote $quote, $id)
    {
        abort(404);
    }
}
