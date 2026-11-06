<?php

namespace Folklore\Pingpong\Tests;

use Folklore\Pingpong\Facades\Pingpong;
use Folklore\Pingpong\PingpongServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [PingpongServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Pingpong' => Pingpong::class];
    }

    protected function defineEnvironment($app): void
    {
        tap($app->make(Repository::class), function (Repository $config): void {
            $config->set('broadcasting.default', 'pingpong');
            $config->set('broadcasting.connections.pingpong', [
                'driver' => 'pingpong',
                'url' => 'https://pingpong.example.com',
                'project' => 'my-app',
                'key' => 'pk_test_0000000000000000',
                'secret' => 'sk_test',
            ]);
        });
    }
}
