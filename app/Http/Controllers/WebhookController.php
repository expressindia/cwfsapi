<?php

namespace App\Http\Controllers;

use App\Models\FulfillmentOrder;

class WebhookController extends Controller
{
    public function index()
    {
        $events = FulfillmentOrder::latest()->paginate(25);

        return view('webhooks.index', compact('events'));
    }
}