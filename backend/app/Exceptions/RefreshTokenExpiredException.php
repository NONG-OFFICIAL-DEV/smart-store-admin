<?php

namespace App\Exceptions;

use Exception;

class RefreshTokenExpiredException extends Exception
{
    public function __construct(string $message = 'Refresh token expired, please log in again.')
    {
        parent::__construct($message, 401);
    }
}
