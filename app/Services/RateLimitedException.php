<?php

namespace App\Services;

use RuntimeException;

/** The AI provider asked us to slow down; retry after $retryAfterSeconds. */
class RateLimitedException extends RuntimeException
{
    public function __construct(public int $retryAfterSeconds, string $message = 'Rate limit exceeded')
    {
        parent::__construct($message);
    }
}
