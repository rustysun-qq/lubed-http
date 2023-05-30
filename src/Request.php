<?php
namespace Lubed\Http;

use Lubed\Http\Streams\Stream;
use Psr\Http\Message\{RequestInterface, StreamInterface, UriInterface};

class Request implements RequestInterface
{
    private $server;

    use MessageTrait;
    use RequestTrait;

    public function __construct(
        string $method,
        $uri,
        array $headers = [],
        $body = null,
        string $version = '1.1'
    ) {
        if (!($uri instanceof UriInterface)) {
            $uri = new Uri($uri);
        }

        $this->method = $method;
        $this->uri = $uri;
        $this->setHeaders($headers);
        $this->protocol = $version;

        if (!$this->hasHeader('Host')) {
            $this->updateHostFromUri();
        }

        if ('' !== $body && null !== $body) {
            $this->stream = Stream::create($body);
        }
    }

    public function withServer($server)
    {
        $this->server=$server;
        return $this;
    }
}
