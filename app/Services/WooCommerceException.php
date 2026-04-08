<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class WooCommerceException extends RuntimeException
{
    public function __construct(string $message, protected int $status = 500, ?Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }
}
