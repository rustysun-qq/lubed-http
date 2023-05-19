<?php
namespace Lubed\Http\Streams;

use Lubed\Http\Exceptions;
use Lubed\Http\HttpException;
use Psr\Http\Message\StreamInterface;

final class Stream implements StreamInterface
{
    private $stream;
    private $seekable;
    private $readable;
    private $writable;
    private $uri;
    private $size;
    private const READ_WRITE_HASH = [
        'read' => [
            'r' => true,
            'w+' => true,
            'r+' => true,
            'x+' => true,
            'c+' => true,
            'rb' => true,
            'w+b' => true,
            'r+b' => true,
            'x+b' => true,
            'c+b' => true,
            'rt' => true,
            'w+t' => true,
            'r+t' => true,
            'x+t' => true,
            'c+t' => true,
            'a+' => true,
        ],
        'write' => [
            'w' => true,
            'w+' => true,
            'rw' => true,
            'r+' => true,
            'x+' => true,
            'c+' => true,
            'wb' => true,
            'w+b' => true,
            'r+b' => true,
            'x+b' => true,
            'c+b' => true,
            'w+t' => true,
            'r+t' => true,
            'x+t' => true,
            'c+t' => true,
            'a' => true,
            'a+' => true,
        ],
    ];

    private function __construct()
    {
    }

    public static function create($body = '') : StreamInterface
    {
        if ($body instanceof StreamInterface) {
            return $body;
        }
        if (is_string($body)) {
            $resource = fopen('php://temp', 'rw+');
            fwrite($resource, $body);
            $body = $resource;
        }
        if (is_resource($body)) {
            $new = new self;
            $new->stream = $body;
            $meta = stream_get_meta_data($new->stream);
            $new->seekable = $meta['seekable'] && 0 === fseek($new->stream, 0, SEEK_CUR);
            $new->readable = isset(self::READ_WRITE_HASH['read'][$meta['mode']]);
            $new->writable = isset(self::READ_WRITE_HASH['write'][$meta['mode']]);
            $new->uri = $new->getMetadata('uri');

            return $new;
        }
        Exceptions::InvalidArgument(
            sprintf('%s:%s',__CLASS__,'First argument to Stream::create() must be a string, resource or StreamInterface.')
        );
    }

    public function __destruct()
    {
        $this->close();
    }

    public function __toString()
    {
        if ($this->isSeekable()) {
            $this->seek(0);
        }

        return $this->getContents();
    }

    public function close() : void
    {
        if (isset($this->stream)) {
            if (is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->detach();
        }
    }

    public function detach()
    {
        if (!isset($this->stream)) {
            return null;
        }
        $result = $this->stream;
        unset($this->stream);
        $this->size = $this->uri = null;
        $this->readable = $this->writable = $this->seekable = false;

        return $result;
    }

    public function getSize() : ?int
    {
        if (null !== $this->size) {
            return $this->size;
        }
        if (!isset($this->stream)) {
            return null;
        }
        if ($this->uri) {
            clearstatcache(true, $this->uri);
        }
        $stats = fstat($this->stream);
        if (isset($stats['size'])) {
            $this->size = $stats['size'];

            return $this->size;
        }

        return null;
    }

    /**
     * @throws HttpException
     */
    public function tell() : int
    {
        if (false === $result = ftell($this->stream)) {
            Exceptions::Runtime(sprintf('%s:%s','Unable to determine stream position'));
        }

        return $result;
    }

    public function eof() : bool
    {
        return !$this->stream || feof($this->stream);
    }

    public function isSeekable() : bool
    {
        return $this->seekable;
    }

    /**
     * @throws HttpException
     */
    public function seek($offset, $whence = SEEK_SET) : void
    {
        if (!$this->seekable) {
            Exceptions::Runtime(sprintf('%s:%s',__CLASS__,'Stream is not seekable'));
        }
        if (-1 === fseek($this->stream, $offset, $whence)) {
            Exceptions::Runtime(
                sprintf('%s:%s',__CLASS__,'Unable to seek to stream position '.$offset.' with whence '.var_export($whence, true))
            );
        }
    }

    public function rewind() : void
    {
        $this->seek(0);
    }

    public function isWritable() : bool
    {
        return $this->writable;
    }

    /**
     * @throws HttpException
     */
    public function write($string) : int
    {
        if (!$this->writable) {
            Exceptions::Runtime(sprintf('%s:%s',__CLASS__,'Cannot write to a non-writable stream'));
        }
        $this->size = null;
        if (false === $result = fwrite($this->stream, $string)) {
            Exceptions::Runtime(sprintf('%s:%s',__CLASS__,'Unable to write to stream'));
        }

        return $result;
    }

    public function isReadable() : bool
    {
        return $this->readable;
    }

    /**
     * @throws HttpException
     */
    public function read($length) : string
    {
        if (!$this->readable) {
            Exceptions::Runtime(sprintf('%s:%s',__CLASS__,'Cannot read from non-readable stream'));
        }

        return fread($this->stream, $length);
    }

    /**
     * @throws HttpException
     */
    public function getContents() : string
    {
        if (!isset($this->stream)) {
            Exceptions::Runtime(sprintf('%s:%s',__CLASS__,'Unable to read stream contents'));
        }
        if (false === $contents = stream_get_contents($this->stream)) {
            Exceptions::Runtime(sprintf('%s:%s',__CLASS__,'Unable to read stream contents'));
        }

        return $contents;
    }

    public function getMetadata($key = null)
    {
        if (!isset($this->stream)) {
            return $key ? null : [];
        }

        $meta = stream_get_meta_data($this->stream);

        if (null === $key) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }
}
