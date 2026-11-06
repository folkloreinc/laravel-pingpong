<?php

namespace Folklore\Pingpong;

use Folklore\Pingpong\Exceptions\PingpongException;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The `pingpong` broadcaster: publishes through Pingpong's HTTP API, and signs private channels in
 * `/broadcasting/auth` after `routes/channels.php` allowed them.
 */
class PingpongBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    /** Socket.IO socket ids, as the protocol defines them. */
    private const SOCKET_ID = '/^[A-Za-z0-9_-]{1,64}$/';

    /**
     * @param  array<string, mixed>  $config  The connection's config, in `broadcasting.connections`.
     */
    public function __construct(
        protected Pingpong $pingpong,
        protected array $config,
    ) {}

    /**
     * Checks that the user may join the channel, with the callbacks of `routes/channels.php`.
     *
     * @param  Request  $request
     */
    public function auth($request): mixed
    {
        $channel = $request->input('channel_name');
        $socketId = $request->input('socket_id');
        if (! is_string($channel) || $channel === '' || ! is_string($socketId) || preg_match(self::SOCKET_ID, $socketId) !== 1) {
            throw new AccessDeniedHttpException;
        }

        $normalized = $this->normalizeChannelName($channel);
        if ($this->isGuardedChannel($channel) && ! $this->retrieveUser($request, $normalized)) {
            throw new AccessDeniedHttpException;
        }

        return parent::verifyUserCanAccessChannel($request, $normalized);
    }

    /**
     * The answer of `/broadcasting/auth`: the signature of the socket and the channel.
     *
     * @param  Request  $request
     * @return array{signature: string}
     */
    public function validAuthenticationResponse($request, $result): array
    {
        return [
            'signature' => Signature::channel(
                $this->pingpong->secret($this->config),
                (string) $request->input('socket_id'),
                (string) $request->input('channel_name'),
            ),
        ];
    }

    /**
     * @param  array<int, mixed>  $channels
     * @param  string  $event
     * @param  array<string, mixed>  $payload
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $socket = Arr::pull($payload, 'socket');
        $client = $this->pingpong->clientFor($this->config);

        try {
            foreach (array_chunk($this->formatChannels($channels), Client::MAX_CHANNELS) as $chunk) {
                $client->publish($chunk, $event, $payload, is_string($socket) && $socket !== '' ? $socket : null);
            }
        } catch (PingpongException $exception) {
            throw new BroadcastException('Pingpong: '.$exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}
