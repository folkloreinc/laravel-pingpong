<?php

namespace Folklore\Pingpong\Exceptions;

use Illuminate\Http\Client\Response;

/** Pingpong answered with an error, as problem details (RFC 9457). */
final class RequestFailed extends PingpongException
{
    /**
     * @param  array<string, list<string>>  $errors  Validation errors by JSON pointer.
     */
    public function __construct(
        public readonly int $status,
        public readonly string $title,
        public readonly ?string $detail = null,
        public readonly array $errors = [],
    ) {
        parent::__construct(sprintf('Pingpong answered %d %s%s', $status, $title, $detail === null ? '' : ': '.$detail), $status);
    }

    public static function fromResponse(Response $response): self
    {
        $problem = $response->json();
        $problem = is_array($problem) ? $problem : [];

        return new self(
            $response->status(),
            is_string($problem['title'] ?? null) ? $problem['title'] : $response->reason(),
            is_string($problem['detail'] ?? null) ? $problem['detail'] : null,
            is_array($problem['errors'] ?? null) ? $problem['errors'] : [],
        );
    }
}
