<?php

namespace Folklore\Pingpong\Tests\Feature;

use Folklore\Pingpong\Signature;
use Folklore\Pingpong\Tests\TestCase;
use Folklore\Pingpong\Tests\ValidatesProtocol;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

final class BroadcasterTest extends TestCase
{
    use ValidatesProtocol;

    private function broadcaster(): Broadcaster
    {
        return $this->app->make(BroadcastFactory::class)->connection('pingpong');
    }

    private function assertSigned(Request $request): void
    {
        $this->assertSame('pk_test_0000000000000000', $request->header('Pingpong-Key')[0] ?? null);
        $header = $request->header('Pingpong-Signature')[0] ?? '';
        $this->assertMatchesRegularExpression('/^t=(\d+),v1=[0-9a-f]{64}$/', $header);
        preg_match('/^t=(\d+)/', $header, $matches);
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $this->assertSame(Signature::request('sk_test', $request->method(), $path, $request->body(), (int) $matches[1]), $header);
    }

    public function test_it_publishes_events_through_the_signed_api(): void
    {
        Http::fake(['pingpong.example.com/*' => Http::response(null, 202)]);

        $this->broadcaster()->broadcast(
            [new Channel('comments.article-1'), new PrivateChannel('users.42')],
            'comment.created',
            ['id' => 42, 'body' => 'Hello'],
        );

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $this->assertSame('POST', $request->method());
            $this->assertSame('https://pingpong.example.com/v1/messages', $request->url());
            $this->assertSigned($request);
            $this->assertSame([
                'channels' => ['comments.article-1', 'private-users.42'],
                'event' => 'comment.created',
                'data' => ['id' => 42, 'body' => 'Hello'],
            ], $request->data());
            $this->assertMatchesProtocol('PublishRequest', $request->data());

            return true;
        });
    }

    public function test_it_leaves_out_the_socket_of_the_sender(): void
    {
        Http::fake(['pingpong.example.com/*' => Http::response(null, 202)]);

        $this->broadcaster()->broadcast(['live'], 'tick', ['socket' => 'Xk2cHvB1mLqQ9a8cAAAB', 'n' => 1]);

        Http::assertSent(fn (Request $request): bool => $request->data() === [
            'channels' => ['live'],
            'event' => 'tick',
            'data' => ['n' => 1],
            'exceptSocketId' => 'Xk2cHvB1mLqQ9a8cAAAB',
        ]);
    }

    public function test_it_sends_at_most_100_channels_per_request(): void
    {
        Http::fake(['pingpong.example.com/*' => Http::response(null, 202)]);

        $this->broadcaster()->broadcast(array_map(fn (int $i): string => "channel.{$i}", range(1, 150)), 'tick');

        Http::assertSentCount(2);
        $sizes = Http::recorded()->map(fn (array $pair): int => count($pair[0]->data()['channels']))->all();
        $this->assertSame([100, 50], $sizes);
    }

    public function test_it_throws_a_broadcast_exception_with_the_problem(): void
    {
        Http::fake(['pingpong.example.com/*' => Http::response([
            'type' => 'about:blank',
            'title' => 'Content Too Large',
            'status' => 413,
            'detail' => "A message is 20000 bytes once serialized, more than the project's limit of 10240.",
        ], 413, ['Content-Type' => 'application/problem+json'])]);

        $this->expectException(BroadcastException::class);
        $this->expectExceptionMessage('Pingpong: Pingpong answered 413 Content Too Large: A message is 20000 bytes');

        $this->broadcaster()->broadcast(['live'], 'tick', ['big' => str_repeat('x', 20000)]);
    }
}
