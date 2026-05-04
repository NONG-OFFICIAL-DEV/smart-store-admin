<?php

// app/Exceptions/TerminalNotTrustedException.php

namespace App\Exceptions;

class TerminalNotTrustedException extends \RuntimeException
{
    public function __construct(
        private readonly string $terminalId,
        string $message = 'This terminal is not trusted.',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getTerminalId(): string
    {
        return $this->terminalId;
    }
}
