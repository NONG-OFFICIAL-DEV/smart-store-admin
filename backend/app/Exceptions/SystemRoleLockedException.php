<?php

namespace App\Exceptions;

use Exception;

class SystemRoleLockedException extends Exception
{
    public function __construct(string $message = 'System roles cannot be modified.')
    {
        parent::__construct($message, 403);
    }
}
