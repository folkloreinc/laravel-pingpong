<?php

namespace Folklore\Pingpong\Facades;

use Folklore\Pingpong\Pingpong as PingpongManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Folklore\Pingpong\Client client(?string $connection = null)
 * @method static array{url: string, project: string, key: string, path: string|null} clientConfig(?string $connection = null)
 * @method static \Folklore\Pingpong\Testing\PingpongFake fake()
 *
 * @see PingpongManager
 */
final class Pingpong extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PingpongManager::class;
    }
}
