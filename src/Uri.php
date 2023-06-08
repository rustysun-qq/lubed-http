<?php
namespace Lubed\Http;

use Lubed\Supports\PSR\HttpMessage\UriInterface;

final class Uri implements UriInterface
{
    private const SCHEMES = [
        'http' => 80,
        'https' => 443,
    ];
    private const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';
    private const CHAR_SUB_DEL_IMS = '!\$&\'\(\)\*\+,;=';
    private const CHAR_PATH = '%:@\/';
    private const CHAR_QUERY_FRAGMENT = '\?';

    private $scheme = '';
    private $userInfo = '';
    private $host = '';
    private $port;
    private $path = '';
    private $query = '';
    private $fragment = '';

    /**
     * @throws HttpException
     */
    public function __construct(string $uri = '')
    {
        if ($uri) {
            if (false === $parts = parse_url($uri)) {
                Exceptions::InvalidArgument(sprintf("%s:Unable to parse URI:%s",__CLASS__,$uri));
            }
            print_r($parts);die("\n---\n");

            $this->scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
            $this->userInfo = $parts['user'] ?? '';
            $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
            $this->port = isset($parts['port']) ? $this->filterPort($parts['port']) : null;
            $this->path = isset($parts['path']) ? $this->filterPath($parts['path']) : '';
            $this->query = isset($parts['query']) ? $this->filterQueryAndFragment($parts['query']) : '';
            $this->fragment = isset($parts['fragment']) ? $this->filterQueryAndFragment(
                $parts['fragment']
            ) : '';
            if (isset($parts['pass'])) {
                $this->userInfo .= ':'.$parts['pass'];
            }
        }
    }

    public function __toString() : string
    {
        return static::createUriString(
            $this->scheme,
            $this->getAuthority(),
            $this->path,
            $this->query,
            $this->fragment
        );
    }

    public function getScheme() : string
    {
        return $this->scheme;
    }

    public function getAuthority() : string
    {
        if ('' === $this->host) {
            return '';
        }
        
        $authority = $this->host;
        if ('' !== $this->userInfo) {
            $authority = $this->userInfo.'@'.$authority;
        }
        if (null !== $this->port) {
            $authority .= ':'.$this->port;
        }

        return $authority;
    }

    public function getUserInfo() : string
    {
        return $this->userInfo;
    }

    public function getHost() : string
    {
        return $this->host;
    }

    public function getPort() : ?int
    {
        return $this->port;
    }

    public function getPath() : string
    {
        return $this->path;
    }

    public function getQuery() : string
    {
        return $this->query;
    }

    public function getFragment() : string
    {
        return $this->fragment;
    }

    /**
     * @throws HttpException
     */
    public function withScheme($scheme) : Uri
    {
        if (!is_string($scheme)) {
            Exceptions::InvalidArgument(sprintf('%s:Scheme must be a string',__CLASS__));
        }
        if ($this->scheme === $scheme = strtolower($scheme)) {
            return $this;
        }
        $new = clone $this;
        $new->scheme = $scheme;
        $new->port = $new->filterPort($new->port);

        return $new;
    }

    public function withUserInfo($user, $password = null) : Uri
    {
        $info = $user;
        if (null !== $password && '' !== $password) {
            $info .= ':'.$password;
        }
        if ($this->userInfo === $info) {
            return $this;
        }
        $new = clone $this;
        $new->userInfo = $info;

        return $new;
    }

    /**
     * @throws HttpException
     */
    public function withHost($host) : Uri
    {
        if (!is_string($host)) {
            Exceptions::InvalidArgument(sprintf('%s:Host must be a string',__CLASS__));
        }
        
        $host = strtolower($host);

        if ($this->host === $host) {
            return $this;
        }

        $new = clone $this;
        $new->host = $host;

        return $new;
    }

    public function withOriginalUri($uri) : Uri
    {
        $new = clone $this;
        $new->uri = $uri;

        return $new;
    }

    public function withPort($port) : Uri
    {
        if ($this->port === $port = $this->filterPort($port)) {
            return $this;
        }
        $new = clone $this;
        $new->port = $port;

        return $new;
    }

    public function withPath($path) : Uri
    {
        if ($this->path === $path = $this->filterPath($path)) {
            return $this;
        }
        $new = clone $this;
        $new->path = $path;

        return $new;
    }

    public function withQuery($query) : Uri
    {
        if ($this->query === $query = $this->filterQueryAndFragment($query)) {
            return $this;
        }
        $new = clone $this;
        $new->query = $query;

        return $new;
    }

    public function withFragment($fragment) : Uri
    {
        if ($this->fragment === $fragment = $this->filterQueryAndFragment($fragment)) {
            return $this;
        }
        $new = clone $this;
        $new->fragment = $fragment;

        return $new;
    }

    private static function createUriString(
        string $scheme,
        string $authority,
        string $path,
        string $query,
        string $fragment
    ) : string {
        $uri = '';
        if ($scheme) {
            $uri .= $scheme.':';
        }
        if ($authority) {
            $uri .= '//'.$authority;
        }
        if ($path) {
            $uri .= static::fixPath($path, $authority);
        }
        if ($query) {
            $uri .= '?'.$query;
        }
        if ('' !== $fragment) {
            $uri .= '#'.$fragment;
        }

        return $uri;
    }

    private static function isNonStandardPort(string $scheme, int $port) : bool
    {
        return !isset(static::SCHEMES[$scheme]) || $port !== static::SCHEMES[$scheme];
    }

    /**
     * @throws HttpException
     */
    private function filterPort($port) : ?int
    {
        if (null === $port) {
            return null;
        }
        $port = (int)$port;
        if (0 > $port || 0xffff < $port) {
            Exceptions::InvalidArgument(
                sprintf('%s:Invalid port: %d. Must be between 0 and 65535', __CLASS__,$port)
            );
        }

        return self::isNonStandardPort($this->scheme, $port) ? $port : null;
    }

    /**
     * @throws HttpException
     */
    private function filterPath($path) : string
    {
        if (!is_string($path)) {
            Exceptions::InvalidArgument(
                sprintf('%s:Path must be a string',__CLASS__)
            );
        }

        return static::filterCharsAndEncode(
            $path,
            static::CHAR_UNRESERVED.static::CHAR_SUB_DEL_IMS.static::CHAR_PATH
        );
    }

    /**
     * @throws HttpException
     */
    private function filterQueryAndFragment($str) : string
    {
        if (!is_string($str)) {
            Exceptions::InvalidArgument(
                sprintf('%s:Query and fragment must be a string',__CLASS__)
            );
        }

        return static::filterCharsAndEncode(
            $str,
            static::CHAR_UNRESERVED.static::CHAR_SUB_DEL_IMS.static::CHAR_PATH.static::CHAR_QUERY_FRAGMENT
        );
    }

    private static function fixPath($path, string $authority) : string
    {
        if ('/' !== $path[0] && $authority) {
            return '/'.$path;
        }
        if (isset($path[1]) && '/' === $path[1] && $authority) {
            return '/'.ltrim($path, '/');
        }

        return $path;
    }

    private static function filterCharsAndEncode(string $str, string $chars)
    {
        $encodedChars = '%(?![A-Fa-f0-9]{2})';
        $format = '/(?:[^%s]++|%s)/';
        $regex = sprintf($format, $chars, $encodedChars);

        return preg_replace_callback(
            $regex,
            function ($matches) {
                return $matches && isset($matches[0]) && $matches[0] ? rawurlencode($matches[0]) : '';
            },
            $str
        );
    }
}
