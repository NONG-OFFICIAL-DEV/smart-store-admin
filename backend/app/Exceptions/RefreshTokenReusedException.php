<?php

namespace App\Exceptions;

use Exception;

// Thrown when an already-revoked (rotated-away-from) refresh token is
// presented again — a strong signal of token theft. RefreshTokenService
// revokes the entire token family before this is thrown.
class RefreshTokenReusedException extends Exception
{
    public function __construct(string $message = 'Session revoked, please log in again.')
    {
        parent::__construct($message, 401);
    }
}
