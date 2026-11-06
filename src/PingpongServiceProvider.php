<?php

namespace Folklore\Pingpong;

use Folklore\Pingpong\Console\StatusCommand;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

final class PingpongServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Pingpong::class, fn (Application $app): Pingpong => new Pingpong(
            $app->make(Factory::class),
            $app->make(Config::class),
        ));
        $this->app->alias(Pingpong::class, 'pingpong');
    }

    public function boot(): void
    {
        $this->callAfterResolving(BroadcastManager::class, function (BroadcastManager $manager): void {
            $manager->extend('pingpong', fn (Application $app, array $config): PingpongBroadcaster => new PingpongBroadcaster(
                $app->make(Pingpong::class),
                $config,
            ));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([StatusCommand::class]);
        }
    }
}
