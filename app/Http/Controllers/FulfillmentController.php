<?php

namespace App\Http\Controllers;

use App\Models\FulfillmentOrder;

class FulfillmentController extends Controller
{
    public function index()
    {
        $fulfillments = FulfillmentOrder::latest()->paginate(25);

        return view('fulfillments.index', compact('fulfillments'));
    }
    public function show(FulfillmentOrder $fulfillment)
    {
        return view('fulfillments.show', compact('fulfillment'));
    }
}