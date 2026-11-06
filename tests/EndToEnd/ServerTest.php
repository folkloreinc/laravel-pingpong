<?php

namespace Folklore\Pingpong\Tests\EndToEnd;

use Folklore\Pingpong\Exceptions\RequestFailed;
use Folklore\Pingpong\Facades\Pingpong;
use Folklore\Pingpong\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

final class NotificationCreated implements ShouldBroadcastNow
{
    public function __construct(public string $title) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.42')];
    }

    public function broadcastAs(): string
    {
        return 'notification';
    }
}

/**
 * Against a real Pingpong server, started by tests/EndToEnd/pingpong.sh. Skipped without
 * PINGPONG_E2E_URL.
 */
final class ServerTest extends TestCase
{
    protected function setUp(): void
    {
        if (! getenv('PINGPONG_E2E_URL')) {
            $this->markTestSkipped('PINGPONG_E2E_URL is not set: start a server with tests/EndToEnd/pingpong.sh.');
        }
        parent::setUp();
        Broadcast::channel('users.{id}', fn (Authenticatable $user, string $id): bool => (string) $user->getAuthIdentifier() === $id);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app->make(Repository::class)->set('broadcasting.connections.pingpong', [
            'driver' => 'pingpong',
            'url' => getenv('PINGPONG_E2E_URL'),
            'project' => getenv('PINGPONG_E2E_PROJECT'),
            'key' => getenv('PINGPONG_E2E_KEY'),
            'secret' => getenv('PINGPONG_E2E_SECRET'),
        ]);
    }

    protected function defineRoutes($router): void
    {
        Route::post('/broadcasting/auth', fn () => Broadcast::auth(request()));
    }

    public function test_a_subscriber_signed_by_laravel_receives_what_laravel_broadcasts(): void
    {
        $process = proc_open(
            ['node', __DIR__.'/subscriber.mjs', (string) getenv('PINGPONG_E2E_URL'), (string) getenv('PINGPONG_E2E_PROJECT'), (string) getenv('PINGPONG_E2E_KEY'), 'private-users.42'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertIsResource($process);
        $read = function () use ($pipes): array {
            $line = fgets($pipes[1]);
            if ($line === false) {
                $this->fail('The subscriber stopped: '.stream_get_contents($pipes[2]));
            }

            return json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        };

        try {
            $socketId = $read()['socketId'] ?? null;
            $this->assertIsString($socketId);

            $signature = $this->actingAs(new GenericUser(['id' => 42]))
                ->postJson('/broadcasting/auth', ['socket_id' => $socketId, 'channel_name' => 'private-users.42'])
                ->assertOk()
                ->json('signature');
            fwrite($pipes[0], $signature."\n");
            $this->assertSame(['ack' => ['ok' => true, 'subscriptionCount' => 1]], $read());
            $this->assertSame(1, Pingpong::client()->channel('private-users.42')->subscriptionCount);

            event(new NotificationCreated('Hello'));

            $this->assertSame(['message' => [
                'channel' => 'private-users.42',
                'event' => 'notification',
                'data' => ['title' => 'Hello'],
            ]], $read());
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }

    public function test_it_manages_the_origins(): void
    {
        $client = Pingpong::client();
        $this->assertSame(['https://example.com'], $client->replaceOrigins(['https://example.com']));
        $this->assertSame(['https://example.com'], $client->origins());
        $this->assertSame([], $client->replaceOrigins([]));
    }

    public function test_pingpong_refuses_a_wrong_secret(): void
    {
        config()->set('broadcasting.connections.pingpong.secret', 'sk_wrong');

        try {
            Pingpong::client()->origins();
            $this->fail('No exception.');
        } catch (RequestFailed $exception) {
            $this->assertSame(401, $exception->status);
        }
    }
}
