<?php

declare(strict_types=1);

namespace MaxMind\Exception;

/**
 * Thrown when the account is out of credits.
 *
 * @final
 */
class InsufficientFundsException extends InvalidRequestException
{
}
