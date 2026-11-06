<?php

namespace Folklore\Pingpong;

use Folklore\Pingpong\Data\Channel;
use Folklore\Pingpong\Exceptions\ConnectionFailed;
use Folklore\Pingpong\Exceptions\RequestFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

/**
 * The HTTP API of a Pingpong project (protocol v1). Every request is signed with the project's
 * secret, which never leaves the application.
 */
class Client
{
    /** The most channels one message can go to. */
    public const MAX_CHANNELS = 100;

    public function __construct(
        protected Factory $http,
        protected string $url,
        protected string $key,
        protected string $secret,
        protected float $timeout = 5,
    ) {}

    /**
     * Sends an event to the subscribers of up to 100 channels.
     *
     * @param  list<string>  $channels
     * @param  string|null  $exceptSocketId  A socket that must not receive it: the sender's (`toOthers()`).
     */
    public function publish(array $channels, string $event, mixed $data = null, ?string $exceptSocketId = null): void
    {
        $this->send('POST', '/v1/messages', self::message($channels, $event, $data, $exceptSocketId));
    }

    /**
     * Publishes up to 100 messages in one request, in order.
     *
     * @param  list<array{channels: list<string>, event: string, data?: mixed, exceptSocketId?: string|null}>  $messages
     */
    public function publishBatch(array $messages): void
    {
        $this->send('POST', '/v1/messages/batch', [
            'messages' => array_map(fn (array $message): array => self::message(
                $message['channels'],
                $message['event'],
                $message['data'] ?? null,
                $message['exceptSocketId'] ?? null,
            ), $messages),
        ]);
    }

    /** How many sockets are subscribed to a channel right now. */
    public function channel(string $channel): Channel
    {
        $body = $this->send('GET', '/v1/channels/'.rawurlencode($channel))->json();

        return new Channel(
            (string) ($body['channel'] ?? $channel),
            (bool) ($body['occupied'] ?? false),
            (int) ($body['subscriptionCount'] ?? 0),
        );
    }

    /**
     * The origins clients may connect from.
     *
     * @return list<string>
     */
    public function origins(): array
    {
        return array_values((array) $this->send('GET', '/v1/origins')->json('origins', []));
    }

    /**
     * Replaces the origins clients may connect from, for example when the application adds a domain.
     *
     * @param  list<string>  $origins
     * @return list<string>
     */
    public function replaceOrigins(array $origins): array
    {
        return array_values((array) $this->send('PUT', '/v1/origins', ['origins' => array_values($origins)])->json('origins', []));
    }

    /**
     * @param  list<string>  $channels
     * @return array<string, mixed>
     */
    private static function message(array $channels, string $event, mixed $data, ?string $exceptSocketId): array
    {
        return array_filter([
            'channels' => array_values($channels),
            'event' => $event,
            'data' => $data,
            'exceptSocketId' => $exceptSocketId,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    protected function send(string $method, string $path, ?array $body = null): Response
    {
        $json = $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $request = $this->http
            ->timeout($this->timeout)
            ->acceptJson()
            ->withHeaders([
                'Pingpong-Key' => $this->key,
                'Pingpong-Signature' => Signature::request($this->secret, $method, $path, $json),
            ]);
        if ($body !== null) {
            $request = $request->withBody($json, 'application/json');
        }

        try {
            $response = $request->send($method, rtrim($this->url, '/').$path);
        } catch (ConnectionException $exception) {
            throw new ConnectionFailed('Pingpong could not be reached: '.$exception->getMessage(), 0, $exception);
        }

        if ($response->failed()) {
            throw RequestFailed::fromResponse($response);
        }

        return $response;
    }
}
