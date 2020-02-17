<?php
declare(strict_types = 1);

namespace Bruteforce\Exception;

use Cake\Http\Exception\ForbiddenException;
use Psr\SimpleCache\InvalidArgumentException as InvalidArgumentInterface;
use Throwable;

/**
 * Exception raised when cache keys are invalid.
 */
class TooManyAttemptsException extends ForbiddenException implements InvalidArgumentInterface {

	/**
	 * Constructor
	 *
	 * @param string|null $message If no message is given 'Internal Server Error' will be the message
	 * @param int|null $code Status code, defaults to 500
	 * @param \Throwable|null $previous The previous exception.
	 */
	public function __construct(?string $message = null, ?int $code = null, ?Throwable $previous = null) {
		if (empty($message)) {
			$message = 'Further verification attempts have been blocked. Please try again in a few minutes.';
		}
		parent::__construct($message, $code, $previous);
	}

}
