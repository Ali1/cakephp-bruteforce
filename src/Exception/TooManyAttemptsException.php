<?php

declare(strict_types=1);

namespace Bruteforce\Exception;

use Cake\Http\Exception\TooManyRequestsException;
use Throwable;

class TooManyAttemptsException extends TooManyRequestsException
{

    public function __construct(?string $message = null, ?int $code = null, ?Throwable $previous = null)
    {
        if (empty($message)) {
            $message = 'Further verification attempts have been blocked. Please try again in a few minutes.';
        }
        parent::__construct($message, $code, $previous);
    }

}
