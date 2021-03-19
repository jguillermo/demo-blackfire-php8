<?php

declare(strict_types=1);

namespace App\Exception;

use LogicException;
use Throwable;

final class Exception extends LogicException
{
    private ?array $extra;

    public function __construct(string $message = '', ?Throwable $previous = null, int $code = 500, ?array $extra = null)
    {
        parent::__construct($message, $code, $previous);
        $this->extra = $extra;
    }

    public function getExtra(): ?array
    {
        return $this->extra;
    }
}
