<?php

namespace Folklore\Pingpong\Tests\Feature;

use Folklore\Pingpong\Facades\Pingpong;
use Folklore\Pingpong\Tests\TestCase;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;

final class CommentCreated implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public function __construct(public int $id) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('comments.article-1'), new PrivateChannel('users.42')];
    }

    public function broadcastAs(): string
    {
        return 'comment.created';
    }
}

final class FakeTest extends TestCase
{
    public function test_it_records_broadcast_events_instead_of_sending_them(): void
    {
        Http::fake();
        $fake = Pingpong::fake();

        event(new CommentCreated(42));

        Http::assertNothingSent();
        $fake->assertPublished('comments.article-1', 'comment.created', fn (array $message): bool => $message['data']['id'] === 42);
        $fake->assertPublished('private-users.42', 'comment.created');
        $fake->assertNotPublished('comments.article-2', 'comment.created');
        $this->assertCount(1, $fake->published());
    }

    public function test_its_assertions_fail_when_they_should(): void
    {
        $fake = Pingpong::fake();
        $fake->assertNothingPublished();
        $fake->setSubscriptionCount('live', 3);
        $this->assertSame(3, Pingpong::client()->channel('live')->subscriptionCount);

        $this->expectException(AssertionFailedError::class);
        $fake->assertPublished('live', 'tick');
    }
}
