<?php
namespace Lubed\Http;

use Lubed\Supports\PSR\HttpMessage\UriInterface;

trait RequestTrait {
    private $method;
    private $requestTarget;
    private $uri;
    private $cookies;
    private $parsedBody;
    private $queryParameters;
    private $files;

    public function getCookies() {
        return $this->cookies;
    }

    public function withCookies(array $cookies) {
        $new=clone $this;
        $new->cookies=$cookies;
        return $new;
    }

    public function getFiles() {
        return $this->files;
    }

    public function withFiles($files) {
        $new=clone $this;
        $new->files=$files;
        return $new;
    }

    public function getParsedBody() {
        return $this->parsedBody;
    }

    public function withParsedBody($data) {
        $new=clone $this;
        if (!$data) {
            $data=$new->getBody()->getContents();
            $jsonDecoder=$data && is_string($data) ? json_decode($data, true) : $data;
            $data=$jsonDecoder ? $jsonDecoder : $data;
        }
        $new->parsedBody=$data;
        return $new;
    }

    public function getQueryParameters() {
        return $this->queryParameters;
    }

    public function withQueryParameters($data) {
        $new=clone $this;
        $new->queryParameters=$data;
        return $new;
    }

    public function getInput(?string $name=null,$default=null) {
        if ($this->queryParameters && $this->parsedBody) {
            $result= array_merge($this->queryParameters, $this->parsedBody);
            if(null===$name){
                return $result;
            }
            return $result[$name]??$default;
        }
        if ($this->parsedBody) {
            $result= $this->parsedBody;
            if(null===$name){
                return $result;
            }
            return $result[$name]??$default;
        }
        if ($this->queryParameters) {
            $result= $this->queryParameters;
            if(null===$name){
                return $result;
            }
            return $result[$name]??$default;
        }
        return [];
    }

    public function getRequestTarget() : string {
        if (null !== $this->requestTarget) {
            return $this->requestTarget;
        }
        if ('' === $target=$this->uri->getPath()) {
            $target='/';
        }
        if ('' !== $this->uri->getQuery()) {
            $target.='?' . $this->uri->getQuery();
        }
        return $target;
    }

    /**
     * @throws HttpException
     */
    public function withRequestTarget($requestTarget) : self {
        if (preg_match('#\s#', $requestTarget)) {
            Exceptions::InvalidArgument(sprintf('%s:Invalid request target provided; cannot contain whitespace', __CLASS__));
        }
        $new=clone $this;
        $new->requestTarget=$requestTarget;
        return $new;
    }

    public function getMethod() : string {
        return $this->method;
    }

    /**
     * @throws HttpException
     */
    public function withMethod($method) : self {
        if (!is_string($method)) {
            Exceptions::InvalidArgument(sprintf('%s:Method must be a string', __CLASS__));
        }
        $new=clone $this;
        $new->method=$method;
        return $new;
    }

    public function getUri() : UriInterface {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost=false) : self {
        if ($uri === $this->uri) {
            return $this;
        }
        $new=clone $this;
        $new->uri=$uri;
        if (!$preserveHost || !$this->hasHeader('Host')) {
            $new->updateHostFromUri();
        }
        return $new;
    }

    /**
     * @See http://tools.ietf.org/html/rfc7230#section-5.4
     */
    private function updateHostFromUri() : void {
        if ('' === $host=$this->uri->getHost()) {
            return;
        }
        if (null !== ($port=$this->uri->getPort())) {
            $host.=':' . $port;
        }
        if (isset($this->headerNames['host'])) {
            $header=$this->headerNames['host'];
        } else {
            $this->headerNames['host']=$header='Host';
        }
        $this->headers=[$header=>[$host]] + $this->headers;
    }
}
