<?php

namespace Folklore\Pingpong\Tests\Unit;

use Folklore\Pingpong\Signature;
use PHPUnit\Framework\TestCase;

/**
 * The expected values come from `@pingpong-io/protocol/signatures`, the server's code: PHP must
 * sign exactly as the server checks.
 */
final class SignatureTest extends TestCase
{
    public function test_it_signs_channels_as_the_server(): void
    {
        $this->assertSame(
            '75843489ac1844241de48da4b801673f892ffe92c35fd9740e7bdb11b9fb008b',
            Signature::channel('sk_test', 'Xk2cHvB1mLqQ9a8cAAAB', 'private-users.42'),
        );
    }

    public function test_it_signs_requests_as_the_server(): void
    {
        $this->assertSame(
            't=1800000000,v1=2263fd39caf83ea86e2681893be83e727e75d68e4d57780187ba5d631edef307',
            Signature::request('sk_test', 'post', '/v1/messages', '{"channels":["a"],"event":"e"}', 1800000000),
        );
        $this->assertSame(
            't=1800000000,v1=89901c76b8e75cbfeac98c663153331ea9fade99e9be4e9a547be923c4ab9db1',
            Signature::request('sk_test', 'GET', '/v1/channels/comments.article-1', '', 1800000000),
        );
    }

    public function test_it_signs_with_the_current_time(): void
    {
        $this->assertMatchesRegularExpression('/^t=(\d+),v1=[0-9a-f]{64}$/', Signature::request('sk', 'GET', '/v1/origins'));
        preg_match('/^t=(\d+)/', Signature::request('sk', 'GET', '/v1/origins'), $matches);
        $this->assertEqualsWithDelta(time(), (int) $matches[1], 2);
    }
}
