<?php

namespace Lubed\Http\Streams;

use Lubed\Http\Exceptions;
use Lubed\Http\HttpException;

class InputStream extends SocketStream
{

    /**
     * @throws HttpException
     */
    public function __construct()
    {
        if (false === ($stream = fopen('php://input', 'r+'))) {
            Exceptions::InvalidArgument(
                sprintf('%s:%s', __CLASS__, error_get_last()['message'])
            );
        }

        parent::__construct($stream);
    }
}
