<?php
namespace Lubed\Http;

use Lum\Http\Streams\SocketStream;
use Lubed\Supports\PSR\HttpMessage\RequestInterface;

class Transport
{
    const CRLFS="\n\r\n\r";
    const CRLF="\n\r";

    private $request;
    private $options;
    private $connection;


    public function __construct(object $options)
    {
        if (!isset($options->timeout)) {
            $options->timeout = 30;
        }
        $this->options = $options;
    }

    public function setRequest(RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * @throws HttpException
     */
    private function getPreferredSslProtocol() : string
    {
        $transports = stream_get_transports();
        $sslTransports = array_filter(
            $transports,
            function ($transport) {
                return (0 === strpos($transport, 'ssl')) or (0 === strpos($transport, 'tls'));
            }
        );
        if (!$sslTransports) {
            $transports = implode(', ', $transports);
            Exceptions::Runtime(
                sprintf('%s: No SSL/TLS transports found, avail transports is: [%s]',__CLASS__,$transports)
            );
        }
        rsort($sslTransports);

        return reset($sslTransports);
    }

    private function buildUri() : string
    {
        if (isset($this->options->proxy)) {
            return $this->options->proxy;
        }
        $isSecure = $this->request->getUri()->getScheme() === 'https';
        $transport = $isSecure ? $this->options->sslProtocol ?? $this->getPreferredSslProtocol() : 'tcp';
        $port = $this->request->getUri()->getPort();
        if (!$port) {
            $port = $isSecure ? 443 : 80;
        }
        $host = $this->request->getUri()->getHost();

        return sprintf('%s://%s:%s', $transport, $host, $port);
    }

    public function connect()
    {
        $errno = $errorString = null;
        $uri = $this->buildUri();
        $timeout = $this->options->timeout;
        $flags = STREAM_CLIENT_CONNECT;
        $arguments = [$uri, $errno, $errorString, $timeout, $flags];
        $context = [];
        if ($this->options->ssl) {
            $context['ssl'] = Misc::convertSslOptionsKeys($this->options->ssl);
        }
        if ($context) {
            $arguments[] = stream_context_create($context);
        }
        if (false === ($this->connection = stream_socket_client(...$arguments))) {
            Exceptions::Network(
                sprintf('%s:%s',__CLASS__,$errorString ? $errorString : 'Unknown network error'), $this->request
            );
        }
        stream_set_blocking($this->connection, true);
    }

    /**
     * @throws HttpException
     */
    public function send($data)
    {
        if ('' === $data) {
            return;
        }
        $string = (string)$data;
        if (false === fwrite($this->connection, $string, strlen($string))) {
            Exceptions::Network(
                sprintf('%s:%s',__CLASS__,error_get_last()['message']), $this->request
            );
        }
    }

    /**
     * @throws HttpException
     */
    public function readMessage() : string
    {
        $message = '';
        while (!stream_get_meta_data($this->connection)['eof']) {
            if (!$this->connection) {
                break;
            }
            $symbol = fgetc($this->connection);
            if (false === $symbol) {
                Exceptions::Network(
                    sprintf('%s:Cannot read data from socket stream',__CLASS__), $this->request
                );
            }
            $message .= $symbol;
            if ( self::CRLFS === substr($message, -4)) {
                break;
            }
        }

        return rtrim($message, self::CRLF);
    }

    public function createBodyStream(array $options = []) : SocketStream
    {
        return new SocketStream($this->connection, $options);
    }

    public function __destruct()
    {
        if ($this->connection) {
            fclose($this->connection);
        }
    }
}
