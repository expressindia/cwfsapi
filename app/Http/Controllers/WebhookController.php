<?php

namespace App\Http\Controllers;

use App\Models\WebhookEvent;

class WebhookController extends Controller
{
    public function index()
    {
        $events = WebhookEvent::latest()->paginate(25);

        return view('webhooks.index', compact('events'));
    }
}