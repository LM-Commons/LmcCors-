<?php

declare(strict_types=1);

namespace LmcCors\Exception;

use DomainException;

class InvalidOriginException extends DomainException implements ExceptionInterface
{
    /**
     * @return self
     */
    public static function fromInvalidHeaderValue(): InvalidOriginException
    {
        return new self('Provided header value supposed to be invalid.');
    }
}
