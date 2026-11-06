# AGENTS.md

`folklore/laravel-pingpong` is the Laravel broadcaster of [Pingpong](https://github.com/folkloreinc/pingpong), a self-hosted, multi-tenant realtime server. It publishes `ShouldBroadcast` events through Pingpong's signed HTTP API, signs private channels in `/broadcasting/auth`, and gives the rest of the API (channel counts, origins) and a fake for tests.

**Language.** This is a public repository. Everything written here is in **English**: code, comments, docs, commit messages, issues and their comments, pull requests and reviews. Never copy private details from the applications that use this package (product plans, infrastructure, hostnames, internal docs, client names). Report security problems privately through a GitHub security advisory, never in a public issue, PR or commit message.

## Status

In development, before 1.0. `main` is the development branch.

- **Plan:** [#1](https://github.com/folkloreinc/laravel-pingpong/issues/1). Pingpong's own plan is [folkloreinc/pingpong#1](https://github.com/folkloreinc/pingpong/issues/1).
- **Protocol:** [Pingpong's `docs/protocol.md`](https://github.com/folkloreinc/pingpong/blob/main/docs/protocol.md). Decisions: [`docs/decisions/`](docs/decisions/README.md).

## Rules

1. **The protocol is the contract.** The package follows the protocol of [`@pingpong-io/protocol`](https://github.com/folkloreinc/pingpong/tree/main/packages/protocol), whose JSON Schema is copied in `tests/protocol` (at the commit in `tests/protocol/VERSION`; refresh it with `tests/protocol/update.sh <pingpong checkout>`). When the package needs something the protocol doesn't have, change the protocol first, in Pingpong. Ignore unknown fields in responses.
2. **Signatures match the server's, byte for byte.** `tests/Unit/SignatureTest.php` checks them against values computed by `@pingpong-io/protocol/signatures`.
3. **The secret never leaves the application.** It signs requests and channels; `Pingpong::clientConfig()` gives pages the URL, the project and the public key only. Never log a secret or a signature.
4. **No static mutable state.** Settings come from `config/broadcasting.php`, read when they are used.
5. **Public API.** The `Pingpong` facade and class, `Client`, `Signature`, the data classes, the exceptions, the fake and the connection's config keys are public. Mark everything else `@internal` or make it `final`.

## Style

- PHP 8.3+ and Laravel 12+ ([decision 0001](docs/decisions/0001-supported-versions.md)). Don't use language features newer than PHP 8.3.
- Typehint parameters, return values and properties.
- Format with [Laravel Pint](https://laravel.com/docs/pint) (`laravel` preset): `composer format`. CI runs `composer lint`.
- Static analysis with [Larastan](https://github.com/larastan/larastan) on `src` and `tests`: `composer analyse`. No baseline.

## Tests

```bash
composer install
composer test
```

- PHPUnit with Orchestra Testbench. Requests are checked against the protocol's JSON Schema (`ValidatesProtocol`).
- **End-to-end tests.** `tests/EndToEnd` runs against a real Pingpong server, and is skipped when `PINGPONG_E2E_URL` is unset. `tests/EndToEnd/pingpong.sh` starts one from a built checkout of `folkloreinc/pingpong` (`npm ci && npm run build`), with SQLite and a project, and prints the variables of the tests. A Node subscriber (`subscriber.mjs`) gets its signature from the package's `/broadcasting/auth` and receives what Laravel broadcasts. CI runs them in the `e2e` job.

  ```bash
  export $(PINGPONG_CHECKOUT=../pingpong tests/EndToEnd/pingpong.sh up)
  vendor/bin/phpunit --testsuite EndToEnd
  tests/EndToEnd/pingpong.sh down
  ```

- **Every change comes with tests.** A bug fix adds a regression test that fails without the fix.
- Before pushing, run `composer lint`, `composer analyse` and `composer test`, and say in the PR what you ran.

## Workflow

- Branch from `main`: `feature/<issue>-<slug>`, `fix/<issue>-<slug>`, `docs/…`, `ci/…`, `refactor/…`, `chore/…`.
- Open a pull request with `Closes #<issue>` or `Part of #<issue>` and the list of checks you ran.
- GitHub Actions runs Pint, Larastan, the tests (PHP 8.3–8.5 × Laravel 12–13) and the end-to-end tests on every pull request. **Merge only when CI is green.** Never skip, disable or weaken a test to get there.
