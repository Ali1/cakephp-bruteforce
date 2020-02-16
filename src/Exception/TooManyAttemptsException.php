<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.7.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace BruteForceProtection\Exception;

use Cake\Http\Exception\ForbiddenException;
use Psr\SimpleCache\InvalidArgumentException as InvalidArgumentInterface;

/**
 * Exception raised when cache keys are invalid.
 */
class TooManyAttemptsException extends ForbiddenException implements InvalidArgumentInterface
{
    /**
     * Constructor
     *
     * @param string|null $message If no message is given 'Internal Server Error' will be the message
     * @param int|null $code Status code, defaults to 500
     * @param \Throwable|null $previous The previous exception.
     */
    public function __construct(?string $message = null, ?int $code = null, ?\Throwable $previous = null)
    {
        if (empty($message)) {
            $message = 'Further verification attempts have been blocked. Please try again in a few minutes.';
        }
        parent::__construct($message, $code, $previous);
    }
}
