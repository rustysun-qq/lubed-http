<?php
namespace Lubed\Http;

use Throwable;

final class Exceptions
{
    //http request failed
    const NETWORK_FAILED = 101001;
    const INVALID_ARGUMENT = 101002;
    const RUN_FAILED=101003;

    /**
     * @throws HttpException
     */
    public static function Network(
        string $message = "",
        $data = null,
        Throwable $previous = null
    ):HttpException {
        throw new HttpException(self::NETWORK_FAILED, $message, $data, $previous);
    }

    /**
     * @throws HttpException
     */
    public static function InvalidArgument(
        string $message = "",
        $data = null,
        Throwable $previous = null):HttpException {
        throw new HttpException(self::INVALID_ARGUMENT, $message, $data, $previous);
    }

    /**
     * @throws HttpException
     */
    public static function Runtime(
        string $message = "",
        $data = null,
        Throwable $previous = null):HttpException {
        throw new HttpException(self::RUN_FAILED, $message, $data, $previous);
    }
}
