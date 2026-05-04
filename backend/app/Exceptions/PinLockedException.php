<?php

// app/Exceptions/PinLockedException.php

namespace App\Exceptions;

class PinLockedException extends \RuntimeException
{
    public function __construct(
        string $message = 'PIN entry is locked.',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
