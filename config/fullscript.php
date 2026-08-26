<?php

return [
    'client_id' => env('FULLSCRIPT_CLIENT_ID'),
    'client_secret' => env('FULLSCRIPT_CLIENT_SECRET'),
    'redirect_uri' => env('FULLSCRIPT_REDIRECT_URI', 'http://127.0.0.1:8000/callback'),
    'scope' => env('FULLSCRIPT_SCOPE', 'catalog:read'),

    // Confirm these values against the API credentials supplied for your Sandbox app.
    'authorization_url' => env('FULLSCRIPT_AUTHORIZATION_URL', 'https://us-snd.fullscript.io/oauth/authorize'),
    'token_url' => env('FULLSCRIPT_TOKEN_URL', 'https://api-us-snd.fullscript.io/api/oauth/token'),
    'refresh_leeway_seconds' => (int) env('FULLSCRIPT_REFRESH_LEEWAY_SECONDS', 300),
];
