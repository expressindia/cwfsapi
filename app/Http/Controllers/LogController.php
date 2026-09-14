<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    public function index()
    {
        $logFile = storage_path('logs/laravel.log');

        $logs = [];

        if (File::exists($logFile)) {

            $content = File::get($logFile);

            // Split Laravel log entries
            preg_match_all(
                '/\[(.*?)\]\s+(\w+)\.(\w+):\s+(.*?)(?=\n\[\d{4}-\d{2}-\d{2}|\z)/s',
                $content,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {

                $logs[] = [
                    'datetime' => $match[1] ?? '',
                    'environment' => $match[2] ?? '',
                    'level' => strtoupper($match[3] ?? ''),
                    'message' => trim($match[4] ?? ''),
                ];
            }
        }

        // Show latest logs first
        $logs = array_reverse($logs);

        return view('logs', compact('logs'));
    }

    public function clear()
    {
        $logFile = storage_path('logs/laravel.log');

        if (File::exists($logFile)) {
            File::put($logFile, '');
        }

        return redirect()
            ->route('logs')
            ->with('success', 'Laravel logs cleared successfully.');
    }
}