<?php

namespace Folklore\Pingpong\Tests\Feature;

use Folklore\Pingpong\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class StatusCommandTest extends TestCase
{
    public function test_it_checks_the_connection(): void
    {
        Http::fake(['*' => Http::response(['origins' => ['https://example.com']])]);

        $this->artisan('pingpong:status')
            ->expectsOutputToContain('my-app')
            ->expectsOutputToContain('https://example.com')
            ->expectsOutputToContain('Pingpong accepts the key and the secret.')
            ->assertSuccessful();
    }

    public function test_it_fails_when_pingpong_refuses_the_signature(): void
    {
        Http::fake(['*' => Http::response(['title' => 'Unauthorized', 'status' => 401, 'detail' => "The signature doesn't match the request."], 401)]);

        $this->artisan('pingpong:status')
            ->expectsOutputToContain("The signature doesn't match the request.")
            ->assertFailed();
    }
}
