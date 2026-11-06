<?php

namespace Folklore\Pingpong\Console;

use Folklore\Pingpong\Exceptions\PingpongException;
use Folklore\Pingpong\Pingpong;
use Illuminate\Console\Command;
use InvalidArgumentException;

/** Checks a connection: its configuration, and a signed request to Pingpong. */
final class StatusCommand extends Command
{
    protected $signature = 'pingpong:status {connection? : A pingpong connection of config/broadcasting.php}';

    protected $description = 'Check the connection to Pingpong';

    public function handle(Pingpong $pingpong): int
    {
        $connection = $this->argument('connection');
        $connection = is_string($connection) ? $connection : null;

        try {
            $config = $pingpong->clientConfig($connection);
            $origins = $pingpong->client($connection)->origins();
        } catch (InvalidArgumentException|PingpongException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Project', $config['project']);
        $this->components->twoColumnDetail('Client URL', $config['url'].($config['path'] === null ? '' : ' ('.$config['path'].')'));
        $this->components->twoColumnDetail('Origins', $origins === [] ? 'any, on the key alone' : implode(', ', $origins));
        $this->components->info('Pingpong accepts the key and the secret.');

        return self::SUCCESS;
    }
}
