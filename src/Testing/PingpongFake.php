<?php

namespace Folklore\Pingpong\Testing;

use Closure;
use Folklore\Pingpong\Client;
use Folklore\Pingpong\Data\Channel;
use PHPUnit\Framework\Assert;

/**
 * Records what the application publishes instead of sending it: `Pingpong::fake()`. Laravel has
 * no `Broadcast::fake()`.
 */
class PingpongFake extends Client
{
    /** @var list<array{channels: list<string>, event: string, data: mixed, exceptSocketId: string|null}> */
    private array $published = [];

    /** @var array<string, int> */
    private array $counts = [];

    /** @var list<string> */
    private array $origins = [];

    public function __construct() {}

    public function publish(array $channels, string $event, mixed $data = null, ?string $exceptSocketId = null): void
    {
        $this->published[] = [
            'channels' => array_values($channels),
            'event' => $event,
            'data' => $data,
            'exceptSocketId' => $exceptSocketId,
        ];
    }

    public function publishBatch(array $messages): void
    {
        foreach ($messages as $message) {
            $this->publish($message['channels'], $message['event'], $message['data'] ?? null, $message['exceptSocketId'] ?? null);
        }
    }

    public function channel(string $channel): Channel
    {
        $count = $this->counts[$channel] ?? 0;

        return new Channel($channel, $count > 0, $count);
    }

    public function origins(): array
    {
        return $this->origins;
    }

    public function replaceOrigins(array $origins): array
    {
        return $this->origins = array_values($origins);
    }

    /** What `channel()` answers for a channel. */
    public function setSubscriptionCount(string $channel, int $count): self
    {
        $this->counts[$channel] = $count;

        return $this;
    }

    /**
     * The messages published, in order.
     *
     * @return list<array{channels: list<string>, event: string, data: mixed, exceptSocketId: string|null}>
     */
    public function published(?string $channel = null, ?string $event = null): array
    {
        return array_values(array_filter($this->published, fn (array $message): bool => ($channel === null || in_array($channel, $message['channels'], true))
            && ($event === null || $message['event'] === $event)));
    }

    /**
     * @param  (Closure(array{channels: list<string>, event: string, data: mixed, exceptSocketId: string|null}): bool)|null  $callback
     */
    public function assertPublished(string $channel, string $event, ?Closure $callback = null): void
    {
        $matching = array_filter($this->published($channel, $event), fn (array $message): bool => $callback === null || $callback($message));
        Assert::assertNotEmpty($matching, "No [{$event}] was published on [{$channel}].");
    }

    public function assertNotPublished(string $channel, string $event): void
    {
        Assert::assertEmpty($this->published($channel, $event), "[{$event}] was published on [{$channel}].");
    }

    public function assertNothingPublished(): void
    {
        Assert::assertEmpty($this->published, count($this->published).' message(s) were published.');
    }
}
