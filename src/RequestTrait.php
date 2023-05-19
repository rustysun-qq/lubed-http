<?php
namespace Lubed\Http;

use Psr\Http\Message\UriInterface;

trait RequestTrait
{
    private $method;
    private $requestTarget;
    private $uri;

    public function getRequestTarget() : string
    {
        if (null !== $this->requestTarget) {
            return $this->requestTarget;
        }
        if ('' === $target = $this->uri->getPath()) {
            $target = '/';
        }
        if ('' !== $this->uri->getQuery()) {
            $target .= '?'.$this->uri->getQuery();
        }

        return $target;
    }

    /**
     * @throws HttpException
     */
    public function withRequestTarget($requestTarget) : self
    {
        if (preg_match('#\s#', $requestTarget)) {
            Exceptions::InvalidArgument(
                sprintf('%s:Invalid request target provided; cannot contain whitespace',__CLASS__)
            );
        }
        $new = clone $this;
        $new->requestTarget = $requestTarget;

        return $new;
    }

    public function getMethod() : string
    {
        return $this->method;
    }

    /**
     * @throws HttpException
     */
    public function withMethod($method) : self
    {
        if (!is_string($method)) {
            Exceptions::InvalidArgument(sprintf('%s:Method must be a string',__CLASS__));
        }

        $new = clone $this;
        $new->method = $method;

        return $new;
    }

    public function getUri() : UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false) : self
    {
        if ($uri === $this->uri) {
            return $this;
        }
        $new = clone $this;
        $new->uri = $uri;
        if (!$preserveHost || !$this->hasHeader('Host')) {
            $new->updateHostFromUri();
        }

        return $new;
    }

    /**
     * @See http://tools.ietf.org/html/rfc7230#section-5.4
     */
    private function updateHostFromUri() : void
    {
        if ('' === $host = $this->uri->getHost()) {
            return;
        }
        if (null !== ($port = $this->uri->getPort())) {
            $host .= ':'.$port;
        }
        if (isset($this->headerNames['host'])) {
            $header = $this->headerNames['host'];
        } else {
            $this->headerNames['host'] = $header = 'Host';
        }
        $this->headers = [$header => [$host]] + $this->headers;
    }
}
