<?php

namespace App\Http\Controllers;

use App\Models\FullscriptToken;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $fullscript = FullscriptToken::find(1);

        $fullscriptConnected = $fullscript !== null
            && filled($fullscript->access_token);

        return view('dashboard', [
            'fullscript' => $fullscript,
            'fullscriptConnected' => $fullscriptConnected,
        ]);
    }
}