<?php
namespace Lubed\Http\Streams;

use Lubed\Http\Exceptions;
use Lubed\Http\HttpException;

class JsonStream extends TextStream
{
    public function __construct($data, $encodingOptions = 79)
    {
        $json = $this->encodeJson($data, $encodingOptions);
        $mime = 'application/json';
        parent::__construct($json);
        $this->withHeader('Content-Type', $mime);
    }

    /**
     * @throws HttpException
     */
    private function encodeJson($data, int $encodingOptions) : string
    {
        // reset error
        json_encode(null);
        $json = json_encode($data, $encodingOptions);
        if (JSON_ERROR_NONE !== json_last_error()) {
            Exceptions::InvalidArgument(
                sprintf(
                    'Unable to encode data to JSON in %s: %s',
                    __CLASS__,
                    json_last_error_msg()
                )
            );
        }

        return $json;
    }
}
