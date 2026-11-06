<?php

namespace Folklore\Pingpong\Data;

/** What Pingpong knows about a channel: how many sockets are subscribed to it right now. */
final readonly class Channel
{
    public function __construct(
        public string $channel,
        public bool $occupied,
        public int $subscriptionCount,
    ) {}
}
