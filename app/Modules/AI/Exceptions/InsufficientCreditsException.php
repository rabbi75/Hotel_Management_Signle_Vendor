<?php

declare(strict_types=1);

namespace App\Modules\AI\Exceptions;

class InsufficientCreditsException extends AiException
{
    public static function for(int $requested, int $available): self
    {
        return new self(__(
            'This workspace has :available AI credit(s) left this month and the request needs :requested. Ask an administrator to raise the allowance.',
            ['available' => $available, 'requested' => $requested],
        ));
    }
}
