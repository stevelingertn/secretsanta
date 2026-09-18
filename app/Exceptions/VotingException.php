<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A domain rule rejected the request. Messages are safe to show to the user.
 * `errors` carries per-item problems (for example, one line per rejected car number).
 */
class VotingException extends RuntimeException
{
    /** @param array<int, string> $errors */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
