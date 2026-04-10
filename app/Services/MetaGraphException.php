<?php

namespace App\Services;

use Exception;
use Throwable;

class MetaGraphException extends Exception
{
    public function __construct(string $message, private readonly int $status = 500, ?Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }
}

