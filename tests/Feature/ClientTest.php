<?php

namespace Folklore\Pingpong\Tests\Feature;

use Folklore\Pingpong\Exceptions\ConnectionFailed;
use Folklore\Pingpong\Exceptions\RequestFailed;
use Folklore\Pingpong\Facades\Pingpong;
use Folklore\Pingpong\Tests\TestCase;
use Folklore\Pingpong\Tests\ValidatesProtocol;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class ClientTest extends TestCase
{
    use ValidatesProtocol;

    public function test_it_reads_a_channel(): void
    {
        Http::fake(['pingpong.example.com/v1/channels/*' => Http::response([
            'channel' => 'comments.article-1',
            'occupied' => true,
            'subscriptionCount' => 12,
        ])]);

        $channel = Pingpong::client()->channel('comments.article-1');

        $this->assertSame(['comments.article-1', true, 12], [$channel->channel, $channel->occupied, $channel->subscriptionCount]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://pingpong.example.com/v1/channels/comments.article-1'
            && $request->body() === '');
    }

    public function test_it_reads_and_replaces_the_origins(): void
    {
        Http::fake(function (Request $request) {
            return Http::response(['origins' => $request->method() === 'PUT' ? $request->data()['origins'] : ['https://example.com']]);
        });

        $this->assertSame(['https://example.com'], Pingpong::client()->origins());
        $this->assertSame(
            ['https://example.com', 'https://*.contest.test'],
            Pingpong::client()->replaceOrigins(['https://example.com', 'https://*.contest.test']),
        );
        Http::assertSent(function (Request $request): bool {
            if ($request->method() === 'PUT') {
                $this->assertMatchesProtocol('Origins', $request->data());
            }

            return true;
        });
    }

    public function test_it_publishes_batches(): void
    {
        Http::fake(['*' => Http::response(null, 202)]);

        Pingpong::client()->publishBatch([
            ['channels' => ['live'], 'event' => 'one'],
            ['channels' => ['live'], 'event' => 'two', 'data' => ['n' => 2], 'exceptSocketId' => 'abc'],
        ]);

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('https://pingpong.example.com/v1/messages/batch', $request->url());
            $this->assertMatchesProtocol('PublishBatchRequest', $request->data());

            return count($request->data()['messages']) === 2;
        });
    }

    public function test_it_reports_problems_and_connection_failures(): void
    {
        Http::fake(['*' => Http::response([
            'title' => 'Unprocessable Content',
            'status' => 422,
            'errors' => ['/channels/0' => ['must match pattern']],
        ], 422)]);

        try {
            Pingpong::client()->publish(['a b'], 'e');
            $this->fail('No exception.');
        } catch (RequestFailed $exception) {
            $this->assertSame(422, $exception->status);
            $this->assertSame(['/channels/0' => ['must match pattern']], $exception->errors);
        }

        Http::fake(fn () => throw new ConnectionException('timed out'));
        $this->expectException(ConnectionFailed::class);
        Pingpong::client()->origins();
    }

    public function test_it_gives_the_page_its_configuration_but_never_the_secret(): void
    {
        $this->assertSame([
            'url' => 'https://pingpong.example.com',
            'project' => 'my-app',
            'key' => 'pk_test_0000000000000000',
            'path' => null,
        ], Pingpong::clientConfig());

        config()->set('broadcasting.connections.pingpong.client', ['url' => 'https://contest.test', 'path' => '/pingpong/socket.io']);
        $this->assertSame(['https://contest.test', '/pingpong/socket.io'], array_values(array_intersect_key(Pingpong::clientConfig(), ['url' => 0, 'path' => 0])));
    }

    public function test_it_explains_a_missing_setting(): void
    {
        config()->set('broadcasting.connections.pingpong.secret', null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PINGPONG_SECRET');
        Pingpong::client();
    }

    public function test_it_explains_a_missing_connection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No pingpong connection named [other]');
        Pingpong::client('other');
    }
}
