<?php

namespace Folklore\Pingpong;

/**
 * The signatures of Pingpong's protocol: of API requests (decision 0007 of Pingpong) and of
 * private channels (decision 0004).
 */
final class Signature
{
    /**
     * The `Pingpong-Signature` header of an API request: `t=<unix time>,v1=<hex HMAC-SHA256>`, the
     * HMAC of `{t}.{method}.{path}.{sha256 of the body}` with the project's secret.
     *
     * @param  string  $path  The request target as sent: path and query string, without the host.
     */
    public static function request(string $secret, string $method, string $path, string $body = '', ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $subject = $timestamp.'.'.strtoupper($method).'.'.$path.'.'.hash('sha256', $body);

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $subject, $secret);
    }

    /**
     * The signature a client needs to join a private channel: the hex HMAC-SHA256 of
     * `{socketId}:{channel}` with the project's secret.
     */
    public static function channel(string $secret, string $socketId, string $channel): string
    {
        return hash_hmac('sha256', $socketId.':'.$channel, $secret);
    }
}
