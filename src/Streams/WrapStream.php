<?php
namespace Lubed\Http\Streams;

use Lubed\Http\Exceptions;
use Lubed\Http\HttpException;
use Psr\Http\Message\StreamInterface;

class WrapStream implements StreamInterface
{
    use MultipartHeaderTrait;

    private $decoratedStream;
    private $offset;

    public function __construct(StreamInterface $decoratedStream, ?int $offset)
    {
        $this->decoratedStream = $decoratedStream;
        $this->offset = (int)$offset;
    }

    public function __toString()
    {
        if ($this->isSeekable()) {
            $this->seek(0);
        }

        return $this->getContents();
    }

    public function close()
    {
        $this->decoratedStream->close();
    }

    public function detach()
    {
        return $this->decoratedStream->detach();
    }

    public function getSize()
    {
        return $this->decoratedStream->getSize() - $this->offset;
    }

    public function tell()
    {
        return $this->decoratedStream->tell() - $this->offset;
    }

    public function eof()
    {
        return $this->decoratedStream->eof();
    }

    public function isSeekable()
    {
        return $this->decoratedStream->isSeekable();
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        if ($whence == SEEK_SET) {
            $this->decoratedStream->seek($offset + $this->offset, $whence);

            return;
        }
        $this->decoratedStream->seek($offset, $whence);
    }

    public function rewind()
    {
        $this->seek(0);
    }

    public function isWritable()
    {
        return $this->decoratedStream->isWritable();
    }

    /**
     * @throws HttpException
     */
    public function write($string)
    {
        if ($this->tell() < 0) {
            Exceptions::Runtime(sprintf('%s:%s',__CLASS__,'Cannot write to stream'));
        }

        return $this->decoratedStream->write($string);
    }

    public function isReadable()
    {
        return $this->decoratedStream->isReadable();
    }

    /**
     * @throws HttpException
     */
    public function read($length)
    {
        if ($this->tell() < 0) {
            Exceptions::Runtime(sprintf('%s:%s',__CLASS__,'Cannot read from stream'));
        }

        return $this->decoratedStream->read($length);
    }

    /**
     * @throws HttpException
     */
    public function getContents()
    {
        if ($this->tell() < 0) {
            Exceptions::Runtime(sprintf('%s:%s',__CLASS__,'Cannot get contents from stream'));
        }

        return $this->decoratedStream->getContents();
    }

    public function getMetadata($key = null)
    {
        return $this->decoratedStream->getMetadata($key);
    }
}
