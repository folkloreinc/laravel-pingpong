# 0002. A Laravel broadcaster, with the connection in `config/broadcasting.php`

Status: accepted (2026-11-06)

## Context

Applications already broadcast with `ShouldBroadcast` events and authorize channels in `routes/channels.php`, with Pusher, Reverb or PubNub. Pingpong's protocol isn't Pusher's (Pingpong's decision 0002), so Laravel's `pusher` and `reverb` drivers don't speak to it.

## Decision

- The package is a broadcaster, `pingpong`, registered with `Broadcast::extend()`. Applications switch with `BROADCAST_CONNECTION=pingpong`, and their events and channels don't change.
- A connection's settings (URL, project, public key, secret, the client's URL and path) live in `config/broadcasting.php`, like every broadcaster's. An application with several projects, such as one per site, declares several connections.
- `/broadcasting/auth` answers `{ "signature": "…" }`: Laravel's route and `Broadcast::auth()`, with the broadcaster's own answer. Socket ids follow Socket.IO's format, not Pusher's.
- `Pingpong::fake()` replaces the API client of every connection, since Laravel has no `Broadcast::fake()`.

## Consequences

- Moving an application to Pingpong is configuration, plus the JavaScript client in its pages.
- The rest of the API (channel counts, origins) goes through `Pingpong::client()`.
