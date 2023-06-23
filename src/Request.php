<?php
namespace Lubed\Http;

use Lubed\Http\Streams\{Stream, InputStream};
use Lubed\Supports\PSR\HttpMessage\{RequestInterface, StreamInterface, UriInterface};
use Lubed\Supports\Ability\Aliasable;

class Request implements RequestInterface, Aliasable {
    private $server;
    private bool $is_routed=false;
    use MessageTrait;
    use RequestTrait;

    public function __construct(string $method='', $uri='', array $headers=[], $body=null,
        string $version='1.1') {
        if (!($uri instanceof UriInterface)) {
            $uri=new Uri($uri);
        }
        $this->method=$method;
        $this->uri=$uri;
        $this->setHeaders($headers);
        $this->protocol=$version;
        if (!$this->hasHeader('Host')) {
            $this->updateHostFromUri();
        }
        if ('' !== $body && null !== $body) {
            $this->stream=Stream::create($body);
        }
    }

    public function getAlias() : string {
        return 'lubed_http_request';
    }

    public function isRouted() : bool {
        return $this->is_routed;
    }

    public function setRouted(bool $is_routed) {
        $this->is_routed=$is_routed;
    }

    public function getServerByName(string $name, $default=null) {
        return $this->server[$name] ?? $default;
    }

    public function withServer($server) {
        $this->server=$server;
        return $this;
    }

    public static function createFromGlobal() {
        $uri=static::initUriByServerEnv();
        $protocol=$_SERVER['SERVER_PROTOCOL'] ?? '';
        $version=$protocol ? str_replace('HTTP/', '', $protocol) : '1.1';
        $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
        $method=$method ? $method : 'GET';
        //get headers
        $headers=function_exists('getallheaders') ? getallheaders() : [];
        if (!$headers) {
            $headers=[];
            foreach ($_SERVER as $name=>$value) {
                if ('HTTP_' === substr($name, 0, 5)) {
                    $header=str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$header]=$value;
                }
            }
        }
        //body
        $body=new InputStream;
        if ('' !== $body && null !== $body) {
            $body=Stream::create($body);
        }
        $request=new self($method, $uri, $headers, $body, $version);
        return $request->withCookies($_COOKIE)->withParsedBody($_POST)->withQueryParameters($_GET)->withFiles($_FILES)->withServer($_SERVER);
    }

    private static function initUriByServerEnv() {
        $uri=new Uri('');
        $env_https=$_SERVER['HTTPS'] ?? 'off';
        if ($env_https) {
            $uri=$uri->withScheme($env_https == 'on' ? 'https' : 'http');
        }
        $env_host=$_SERVER['HTTP_HOST'];
        $env_host=$env_host ? $env_host : $_SERVER['SERVER_NAME'];
        if ($env_host) {
            $host_info=explode(':', $env_host);
            $uri=$uri->withHost($host_info[0] ?? $env_host);
        }
        $env_port=$_SERVER['SERVER_PORT'];
        if ($env_port) {
            $uri=$uri->withPort($env_port);
        }
        $env_uri=$_SERVER['REQUEST_URI'];
        if ($env_uri) {
            $uri=$uri->withOriginalUri($env_uri);
        }
        $path_info=$_SERVER['PATH_INFO'] ?? null;
        $path=$path_info ? $path_info : $env_uri;
        if ($path) {
            $path=current(explode('?', $path));
            $uri=$uri->withPath($path);
        }
        //TODO:remove默认格式
        $format='json';
        if ($path && false !== strpos('.', $format)) {
            $path_info=explode('.', $path);
            $format=$path_info && is_array($path_info) ? array_pop($path_info) : $format;
        }
        $env_query=$_SERVER['QUERY_STRING'] ?? '';
        if ($env_query) {
            $uri=$uri->withQuery($env_query);
        }
        return $uri;
    }
}
