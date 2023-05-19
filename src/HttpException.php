<?php
namespace Lubed\Http;

use Exception;
use Throwable;

final class HttpException extends Exception
{
    private $data;
    public function __construct(
        int $code = 0,
        string $message = "",
        $data = null,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }
}