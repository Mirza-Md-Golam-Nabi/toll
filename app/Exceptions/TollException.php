<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Business-rule violation with a user-facing (translated) message.
 */
class TollException extends RuntimeException
{
    public static function because(string $key, array $replace = []): self
    {
        return new self(__($key, $replace));
    }
}
