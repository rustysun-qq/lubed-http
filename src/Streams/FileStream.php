<?php
namespace Lubed\Http\Streams;

use finfo;
use Lubed\Http\Exceptions;
use Lubed\Http\HttpException;

class FileStream extends SocketStream
{
    private $filename = '';

    /**
     * @throws HttpException
     */
    public function __construct(string $path, string $filename = '')
    {
        if (false === ($stream = fopen($path, 'rb'))) {
            Exceptions::InvalidArgument(
                sprintf('%s:%s',__CLASS__, error_get_last()['message'])
            );
        }
        if (empty($filename)) {
            $filename = basename($path);
        }
        $this->filename = $filename;
        parent::__construct($stream);
        $mime = (new finfo)->file($path, FILEINFO_MIME_TYPE);
        if (false === $mime) {
            $mime = 'application/binary';
        }
        $this->withHeader('Content-Type', $mime);
    }

    public function getClientFilename() : string
    {
        return $this->filename;
    }
}
