# 0001. PHP 8.3+ and Laravel 12+

Status: accepted (2026-11-06)

## Decision

`laravel-pingpong` supports PHP 8.3+ and Laravel 12+, like `folklore/laravel-eclair` and the new major versions of the Folklore packages. CI tests PHP 8.3, 8.4 and 8.5 with Laravel 12 and 13. Larastan analyses the code against PHP 8.3.

## Consequences

Applications on older versions keep their current broadcaster until they upgrade, or a 0.x line for them becomes a new decision.
