<?php

namespace Folklore\Pingpong\Tests\Feature;

use Folklore\Pingpong\Signature;
use Folklore\Pingpong\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

final class AuthTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::post('/broadcasting/auth', fn () => Broadcast::auth(request()));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Broadcast::channel('users.{id}', fn (Authenticatable $user, string $id): bool => (string) $user->getAuthIdentifier() === $id);
    }

    private function user(int $id): GenericUser
    {
        return new GenericUser(['id' => $id]);
    }

    public function test_it_signs_a_private_channel_the_user_may_join(): void
    {
        $response = $this->actingAs($this->user(42))->postJson('/broadcasting/auth', [
            'socket_id' => 'Xk2cHvB1mLqQ9a8cAAAB',
            'channel_name' => 'private-users.42',
        ]);

        $response->assertOk()->assertExactJson([
            'signature' => Signature::channel('sk_test', 'Xk2cHvB1mLqQ9a8cAAAB', 'private-users.42'),
        ]);
    }

    public function test_it_refuses_other_users_guests_and_bad_requests(): void
    {
        $request = ['socket_id' => 'Xk2cHvB1mLqQ9a8cAAAB', 'channel_name' => 'private-users.42'];

        $this->actingAs($this->user(7))->postJson('/broadcasting/auth', $request)->assertForbidden();
        $this->app->make('auth')->forgetGuards();
        $this->postJson('/broadcasting/auth', $request)->assertForbidden();
        $this->actingAs($this->user(42))->postJson('/broadcasting/auth', [...$request, 'socket_id' => '123.456 bad'])->assertForbidden();
        $this->actingAs($this->user(42))->postJson('/broadcasting/auth', ['socket_id' => 'Xk2cHvB1mLqQ9a8cAAAB'])->assertForbidden();
        $this->actingAs($this->user(42))->postJson('/broadcasting/auth', [...$request, 'channel_name' => 'private-unknown.1'])->assertForbidden();
    }
}
