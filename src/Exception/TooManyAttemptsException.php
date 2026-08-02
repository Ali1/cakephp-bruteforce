<?php
declare(strict_types=1);

namespace Bruteforce\Exception;

use Cake\Http\Exception\HttpException;
use Throwable;

class TooManyAttemptsException extends HttpException
{
    protected int $_defaultCode = 429;

    /**
     * @param string|null $message The message, or null for the default wording.
     * @param int|null $code The exception code.
     * @param \Throwable|null $previous The previous exception, if any.
     */
    public function __construct(?string $message = null, ?int $code = null, ?Throwable $previous = null)
    {
        if (empty($message)) {
            $message = 'Further verification attempts have been blocked. Please try again in a few minutes.';
        }
        parent::__construct($message, $code, $previous);
    }
}
