# Laravel Pingpong

The Laravel broadcaster of [Pingpong](https://github.com/folkloreinc/pingpong), a self-hosted, multi-tenant realtime server built on Socket.IO. `ShouldBroadcast` events and `routes/channels.php` work as with any broadcaster.

> **Status: in development.** Nothing is released yet. The plan is [#1](https://github.com/folkloreinc/laravel-pingpong/issues/1).

## Installation

```bash
composer require folklore/laravel-pingpong
```

Add a connection to `config/broadcasting.php`, and set `BROADCAST_CONNECTION=pingpong`:

```php
'pingpong' => [
    'driver' => 'pingpong',
    'url' => env('PINGPONG_URL'),         // the server, for the API
    'project' => env('PINGPONG_PROJECT'),
    'key' => env('PINGPONG_KEY'),         // public: pages send it when they connect
    'secret' => env('PINGPONG_SECRET'),   // never leaves the server
    'client' => [
        'url' => env('PINGPONG_CLIENT_URL'),   // what browsers use, when it differs from url
        'path' => env('PINGPONG_CLIENT_PATH'), // e.g. /pingpong/socket.io behind the site's proxy
    ],
],
```

Enable the auth route as usual (`withBroadcasting()` in `bootstrap/app.php`, or `Broadcast::routes()`), and give the page what the JavaScript client needs, never the secret:

```php
$props['pingpong'] = Pingpong::clientConfig(); // url, project, key, path
```

```ts
import { Pingpong, laravelAuthorizer } from '@pingpong-io/client';

const pingpong = new Pingpong({ ...props.pingpong, authorize: laravelAuthorizer() });
pingpong.subscribe('private-users.42', (message) => console.log(message.event, message.data));
```

## Broadcasting

Events broadcast as with any driver, `toOthers()` included. Private channels are signed by `/broadcasting/auth` after `routes/channels.php` allowed them. Presence channels aren't supported by Pingpong yet.

The rest of the API:

```php
Pingpong::client()->channel('comments.article-1')->subscriptionCount; // readers right now
Pingpong::client()->replaceOrigins(['https://example.com', 'https://*.example.com']);
Pingpong::client()->publish(['live'], 'tick', ['n' => 1]); // without an event class
```

`php artisan pingpong:status` checks a connection: its settings, and a signed request.

## Testing

Laravel has no `Broadcast::fake()`. `Pingpong::fake()` records what is published instead:

```php
$fake = Pingpong::fake();

// …

$fake->assertPublished('comments.article-1', 'comment.created', fn (array $message) => $message['data']['id'] === 42);
$fake->assertNothingPublished();
```

## License

[MIT](LICENSE)
