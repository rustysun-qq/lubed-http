<?php
namespace Lubed\Http;

use Lubed\Supports\PSR\HttpMessage\{ResponseInterface};

class Response implements ResponseInterface
{
    use MessageTrait;

    private const PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        511 => 'Network Authentication Required',
    ];

    private $reasonPhrase;
    private $statusCode;
    private $content;

    public function __construct(
        $body = null,
        int $status = 200,
        string $reason = null,
        array $headers = [],
        string $version = '1.1'
    ) {
        $this->reasonPhrase = '';
        if ('' !== $body && null !== $body) {
            $this->content = $body;
        }
        $this->statusCode = $status;
        $this->setHeaders($headers);
        if (null === $reason && isset(self::PHRASES[$this->statusCode])) {
            $this->reasonPhrase = self::PHRASES[$status];
        } else {
            $this->reasonPhrase = $reason ?? '';
        }
        $this->protocol = $version;
    }

    public function getStatusCode() : int
    {
        return $this->statusCode;
    }

    public function getReasonPhrase() : string
    {
        return $this->reasonPhrase;
    }

    /**
     * @throws HttpException
     */
    public function withStatus($code, $reasonPhrase = '') : Response
    {
        if (!is_int($code) && !is_string($code)) {
            Exceptions::InvalidArgument(sprintf('%s:Status code has to be an integer',__CLASS__));
        }
        $code = (int)$code;
        if ($code < 100 || $code > 599) {
            Exceptions::InvalidArgument(sprintf('%s:Status code has to be an integer between 100 and 599',__CLASS__));
        }
        $new = clone $this;
        $new->statusCode = $code;
        if ((null === $reasonPhrase || '' === $reasonPhrase) && isset(self::PHRASES[$new->statusCode])) {
            $reasonPhrase = self::PHRASES[$new->statusCode];
        }
        $new->reasonPhrase = $reasonPhrase;

        return $new;
    }

    public function sendHeaders()
    {
        if (headers_sent()) {
            return $this;
        }

        // headers
        foreach ($this->getHeaders() as $name => $value) {
            header($name.': '.$value, false, $this->statusCode);
        }

        header(sprintf('HTTP/%s %s %s', $this->getProtocolVersion(), $this->statusCode, $this->reasonPhrase), true, $this->statusCode);

        return $this;
    }

    public function sendContent()
    {
        echo $this->getContent();

        return $this;
    }

    public function send()
    {
        $this->sendHeaders();
        $this->sendContent();

        if (\function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            static::closeOutputBuffers(0, true);
        }

        return $this;
    }

    public static function closeOutputBuffers(int $targetLevel, bool $flush): void
    {
        $status = \ob_get_status(true);
        $level = \count($status);
        $flags = \PHP_OUTPUT_HANDLER_REMOVABLE | ($flush ? \PHP_OUTPUT_HANDLER_FLUSHABLE : \PHP_OUTPUT_HANDLER_CLEANABLE);

        while ($level-- > $targetLevel && ($s = $status[$level]) && (!isset($s['del']) ? !isset($s['flags']) || ($s['flags'] & $flags) === $flags : $s['del'])) {
            if ($flush) {
                ob_end_flush();
            } else {
                ob_end_clean();
            }
        }
    }

    protected function setContent(string $content){
        $this->content=$content;
    }

    protected function getContent(){
        return $this->content;
    }
}
