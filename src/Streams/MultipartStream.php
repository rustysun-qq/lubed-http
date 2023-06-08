<?php
namespace Lubed\Http\Streams;

use ArrayIterator;
use Lubed\Http\Exceptions;
use Lubed\Http\HttpException;
use Lubed\Http\Misc;
use Lubed\Suports\PSR\HttpMessage\StreamInterface;

class MultipartStream implements StreamInterface
{
    const CRLF = "\n\r";
    const CRLFS = "\n\r\n\r";
    private $boundary;
    private $arrayIterator;
    private $streamCursor;
    private $streamIndex;
    private $patterns = [
        'plain' => ('Content-Disposition: form-data; name="%s"%s'.self::CRLFS.'%s'),
        'file' => ('Content-Disposition: form-data; name="%s"; filename="%s"%s'.self::CRLFS),
    ];

    public function __construct(array $data)
    {
        $this->arrayIterator = new ArrayIterator;
        $data = $this->arrayToPlain($data);
        foreach ($data as $key => $value) {
            $isFileStream = $value instanceof FileStream;
            $pattern = $this->patterns[$isFileStream ? 'file' : 'plain'];
            $metaHeader = ($value instanceof SocketStream) ? Misc::serializePsr7Headers(
                $value->getHeaders()
            ) : '';
            if ($metaHeader) {
                $metaHeader = self::CRLF.$metaHeader;
            }
            $meta = sprintf('--%s%s',$this->getBoundary(),self::CRLF);
            $meta .= ($isFileStream ? sprintf(
                $pattern,
                $key,
                $value->getClientFilename(),
                $metaHeader
            ) : sprintf($pattern, $key, $metaHeader, !($value instanceof StreamInterface) ? $value : ''));
            $metaStream = new TextStream($meta);
            $this->arrayIterator->append($metaStream);
            if ($value instanceof StreamInterface) {
                $this->arrayIterator->append($value);
            }
            $this->arrayIterator->append(new TextStream(self::CRLF));
        }
        if ($data) {
            $this->arrayIterator->append(
                new TextStream(sprintf('--%s--',$this->getBoundary()))
            );
            $this->streamIndex = 0;
            $this->streamCursor = $this->arrayIterator->offsetGet(0);
        }
    }

    /**
     * @throws HttpException
     */
    private function arrayToPlain(array $data, $prefix = '')
    {
        $result = [];
        foreach ($data as $key => $value) {
            if ($value instanceof MultipartStream) {
                Exceptions::Runtime(
                    sprintf('%s:%s',__CLASS__,' disabled in nested multipart data')
                );
            }
            if ($prefix) {
                $index = $prefix.'['.$key.']';
            } else {
                $index = $key;
            }
            if (is_array($value)) {
                $result = $result + $this->arrayToPlain($value, $index);
            } else {
                $result[$index] = $value;
            }
        }

        return $result;
    }

    public function getContents()
    {
        $data = '';
        foreach ($this->arrayIterator as $stream) {
            $data .= $stream->getContents();
        }

        return $data;
    }

    public function read($length)
    {
        if (!$this->streamCursor) {
            return '';
        }
        $data = $this->streamCursor->read($length);
        $bytes = strlen($data);
        if ($bytes < $length) {
            if (!$this->eof()) {
                $this->streamCursor = $this->arrayIterator->offsetGet(++$this->streamIndex);
                $data .= $this->read($length - $bytes);
            }
        }

        return $data;
    }

    public function isReadable()
    {
        return true;
    }

    public function eof()
    {
        if (!$this->streamCursor) {
            return true;
        }

        return ($this->streamCursor and
            $this->streamCursor->eof() and $this->arrayIterator->count() === $this->streamIndex + 1);
    }

    public function detach()
    {
        foreach ($this->arrayIterator as $stream) {
            $stream->detach();
        }
    }

    public function close()
    {
        foreach ($this->arrayIterator as $stream) {
            $stream->close();
        }
    }

    /**
     * @throws HttpException
     */
    public function tell()
    {
        Exceptions::Runtime(sprintf('%s:%s',__CLASS__,'Cannot get current position'));
    }

    public function isWritable()
    {
        return false;
    }

    /**
     * @throws HttpException
     */
    public function write($string)
    {
        Exceptions::Runtime(sprintf('%s:%s',__CLASS__,'Stream does not support writing'));
    }

    public function rewind()
    {
        foreach ($this->arrayIterator as $stream) {
            $stream->rewind();
        }
    }

    /**
     * @throws HttpException
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        Exceptions::Runtime(
            sprintf('%s:%s',__CLASS__,'Stream does not support seeking')
        );
    }

    public function isSeekable()
    {
        return false;
    }

    public function getSize()
    {
        $size = 0;
        foreach ($this->arrayIterator as $stream) {
            $size += $stream->getSize();
        }

        return $size;
    }

    public function getBoundary()
    {
        if (null === $this->boundary) {
            $abc = implode(
                array_merge(
                    range('A', 'Z'),
                    range('a', 'z'),
                    range(0, 9)
                )
            );
            $this->boundary = substr(str_shuffle($abc), -12);
        }

        return $this->boundary;
    }

    public function getMetadata($key = null)
    {
        return null;
    }

    public function __toString()
    {
        return $this->getContents();
    }
}
