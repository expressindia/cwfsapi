<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FullscriptStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_endpoint_does_not_expose_tokens(): void
    {
        $response = $this->getJson('/fullscript/status');

        $response->assertOk()
            ->assertJsonPath('connected', false)
            ->assertJsonMissing(['access_token', 'refresh_token']);
    }
}
