<?php

namespace Folklore\Pingpong;

use Folklore\Pingpong\Testing\PingpongFake;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory;
use InvalidArgumentException;

/**
 * The entry point of the package: the API client of a broadcasting connection, the configuration
 * a page gives the JavaScript client, and the fake for tests.
 */
class Pingpong
{
    private ?PingpongFake $fake = null;

    public function __construct(
        protected Factory $http,
        protected Config $config,
    ) {}

    /**
     * The API client of a `pingpong` connection of `config/broadcasting.php`: the default
     * connection, or the one named `pingpong`.
     */
    public function client(?string $connection = null): Client
    {
        return $this->clientFor($this->connection($connection));
    }

    /**
     * What a page gives `@pingpong-io/client`: the URL, the project, its public key and the path.
     * Never the secret.
     *
     * @return array{url: string, project: string, key: string, path: string|null}
     */
    public function clientConfig(?string $connection = null): array
    {
        $config = $this->connection($connection);
        $client = is_array($config['client'] ?? null) ? $config['client'] : [];

        $url = $client['url'] ?? null;

        return [
            'url' => is_string($url) && $url !== '' ? $url : $this->required($config, 'url'),
            'project' => $this->required($config, 'project'),
            'key' => $this->required($config, 'key'),
            'path' => isset($client['path']) && $client['path'] !== '' ? (string) $client['path'] : null,
        ];
    }

    /**
     * Records what is published instead of sending it, for tests.
     */
    public function fake(): PingpongFake
    {
        return $this->fake = new PingpongFake;
    }

    public function isFake(): bool
    {
        return $this->fake !== null;
    }

    /**
     * @internal The API client of a connection's config.
     *
     * @param  array<string, mixed>  $config
     */
    public function clientFor(array $config): Client
    {
        if ($this->fake !== null) {
            return $this->fake;
        }

        return new Client(
            $this->http,
            $this->required($config, 'url'),
            $this->required($config, 'key'),
            $this->secret($config),
            (float) ($config['timeout'] ?? 5),
        );
    }

    /**
     * @internal The secret of a connection's config.
     *
     * @param  array<string, mixed>  $config
     */
    public function secret(array $config): string
    {
        return $this->required($config, 'secret');
    }

    /**
     * @return array<string, mixed>
     */
    protected function connection(?string $name): array
    {
        if ($name === null) {
            $default = $this->config->get('broadcasting.default');
            $name = is_string($default) && $this->config->get("broadcasting.connections.{$default}.driver") === 'pingpong' ? $default : 'pingpong';
        }
        $config = $this->config->get("broadcasting.connections.{$name}");
        if (! is_array($config) || ($config['driver'] ?? null) !== 'pingpong') {
            throw new InvalidArgumentException("No pingpong connection named [{$name}] in config/broadcasting.php.");
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function required(array $config, string $key): string
    {
        $value = $config[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("The pingpong connection has no {$key}: set it in config/broadcasting.php (PINGPONG_".strtoupper($key).').');
        }

        return $value;
    }
}
