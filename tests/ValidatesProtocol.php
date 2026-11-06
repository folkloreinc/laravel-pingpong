<?php

namespace Folklore\Pingpong\Tests;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Checks values against the JSON Schema of Pingpong's protocol, copied in tests/protocol at the
 * commit in tests/protocol/VERSION.
 */
trait ValidatesProtocol
{
    private static ?Validator $protocolValidator = null;

    protected function assertMatchesProtocol(string $definition, mixed $value): void
    {
        if (self::$protocolValidator === null) {
            self::$protocolValidator = new Validator;
            self::$protocolValidator->resolver()?->registerFile('urn:pingpong:protocol:v1', __DIR__.'/protocol/pingpong.schema.json');
        }
        $data = json_decode(json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), false, 512, JSON_THROW_ON_ERROR);
        $result = self::$protocolValidator->validate($data, 'urn:pingpong:protocol:v1#/$defs/'.$definition);

        $this->assertTrue($result->isValid(), $result->hasError()
            ? json_encode((new ErrorFormatter)->format($result->error()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: ''
            : '');
    }
}
