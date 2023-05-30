<?php
namespace Lubed\Http\Streams;

use Lubed\Http\Exceptions;
use Lubed\Http\HttpException;
use Psr\Http\Message\StreamInterface;

class SocketStream implements StreamInterface
{
    use MultipartHeaderTrait;
    private $stream;
    private $options;

    public function __construct($stream, array $options = [])
    {
        $this->options = (object)$options;
        $this->stream = $stream;
    }

    public function getContents()
    {
        return stream_get_contents($this->stream);
    }

    /**
     * @throws HttpException
     */
    public function tell()
    {
        if (false === ($position = ftell($this->stream))) {
            Exceptions::Runtime(sprintf('%s:Cannot get stream offset',__CLASS__));
        }

        return $position;
    }

    /**
     * @throws HttpException
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        if (-1 === fseek($this->stream, $offset, $whence)) {
            Exceptions::Runtime(
                sprintf('%s:Stream does not support seeking',__CLASS__)
            );
        }
    }

    public function rewind()
    {
        $this->seek(0);
    }

    public function eof()
    {
        return feof($this->stream);
    }

    public function getMetadata($key = null)
    {
        if (0 === func_num_args()) {
            return stream_get_meta_data($this->stream);
        }

        return stream_get_meta_data($this->stream)[$key];
    }

    /**
     * @throws HttpException
     */
    public function read($length)
    {
        if (false === ($data = fread($this->stream, $length))) {
            Exceptions::Runtime(sprintf('%s:Cannot read from stream',__CLASS__));
        }

        return $data;
    }

    public function isSeekable()
    {
        return $this->getMetadata('seekable');
    }

    public function getSize()
    {
        $size = (int)fstat($this->stream)['size'];
        if (!$size) {
            if (isset($this->options->contentLength)) {
                $size = $this->options->contentLength;
            }
        }

        return $size;
    }

    public function close()
    {
        $stream = $this->detach();
        fclose($stream);
    }

    public function detach()
    {
        $stream = $this->stream;
        $this->stream = null;

        return $stream;
    }

    public function isReadable()
    {
        $mode = $this->getMetadata('mode');

        return (strstr($mode, 'r') or strstr($mode, '+'));
    }

    public function isWritable()
    {
        $mode = $this->getMetadata('mode');

        return (strstr($mode, 'x') or strstr($mode, 'w') or strstr($mode, 'c') or strstr($mode, 'a') or
            strstr($mode, '+'));
    }

    /**
     * @throws HttpException
     */
    public function write($string)
    {
        if (false === ($length = fwrite($this->stream, $string))) {
            Exceptions::Runtime(sprintf('%s:Cannot write to stream',__CLASS__));
        }

        return $length;
    }

    public function __toString()
    {
        return (string)$this->getContents();
    }
}
