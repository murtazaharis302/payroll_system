<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class InvalidStatusTransitionException extends HttpException
{
    public function __construct(string $fromStatus, string $toStatus, string $message = '')
    {
        $defaultMsg = "Illegal status transition from '{$fromStatus}' to '{$toStatus}'.";
        parent::__construct(409, $message ?: $defaultMsg, null, [], 409);
    }
}
