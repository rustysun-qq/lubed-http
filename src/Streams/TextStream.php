<?php
namespace Lubed\Http\Streams;

use Lubed\Http\Exceptions;

class TextStream extends SocketStream
{
    public function __construct(string $stream, array $options = [])
    {
        $options = (object)$options;
        if (!isset($options->mime)) {
            $options->mime = 'text/plain';
        }
        $dataUrl = 'data:'.$options->mime;
        if (isset($options->encoding)) {
            $dataUrl .= ';'.$options->encoding;
        }
        $dataUrl .= ','.$stream;
        if (false === ($stream = fopen($dataUrl, 'rb'))) {
            Exceptions::InvalidArgument(
                sprintf('%s:%s',__CLASS__,error_get_last()['message'])
            );
        }
        parent::__construct($stream);
        $this->withHeader('Content-Type', $options->mime);
    }
}
