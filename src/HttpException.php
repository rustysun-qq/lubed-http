<?php
namespace Lubed\Http;

use Lubed\Exceptions\RuntimeException;

final class HttpException extends RuntimeException
{
    private $data;
    public function __construct(
        int $code = 0,
        string $message = "",
        $options = [],
        Throwable $previous = null
    ) {
        parent::__construct($code,$message, $options, $previous);
    }
}