<?php

namespace App\Services;

use RuntimeException;

/**
 * An AI call that failed for a reason worth explaining to the user.
 *
 * The message is written for the person in the chat window. The upstream
 * response body never reaches them: it can carry the API key and Google's
 * internal error details, so it is logged instead.
 */
class AiServiceException extends RuntimeException
{
}
