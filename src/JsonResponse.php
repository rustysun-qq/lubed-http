<?php
namespace Lubed\Http;
class JsonResponse extends Response {
    protected $data;
    protected $callback;
    const DEFAULT_ENCODING_OPTIONS=JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT |
    JSON_UNESCAPED_UNICODE;
    protected $encodingOptions=self::DEFAULT_ENCODING_OPTIONS;

    public function __construct($data=null, int $status=200, array $headers=[], bool $json=false) {
        parent::__construct('', $status, '', $headers);
        if (null === $data) {
            $data=new \ArrayObject;
        }
        $json ? $this->setJson($data) : $this->setData($data);
    }

    public static function create($data=null, $status=200, $headers=[]) {
        $is_json=null !== $data && is_string($data) ? true : false;
        return new static($data, $status, $headers, $is_json);
    }

    public function setJson($json) {
        $this->data=$json;
        return $this->update();
    }

    public function setData($data):JsonResponse {
        try {
            $data=json_encode($data, $this->encodingOptions);
        } catch(\Exception $e) {
            if ('Exception' === \get_class($e) && 0 === strpos($e->getMessage(), 'Failed calling ')) {
                throw $e->getPrevious() ?: $e;
            }
            throw $e;
        }
        if (\PHP_VERSION_ID >= 70300 && (JSON_THROW_ON_ERROR & $this->encodingOptions)) {
            return $this->setJson($data);
        }
        if (JSON_ERROR_NONE !== json_last_error()) {
            Exceptions::InvalidArgument(json_last_error_msg(), ['method'=>__METHOD__]);
        }
        return $this->setJson($data);
    }

    public function getEncodingOptions() {
        return $this->encodingOptions;
    }

    public function setEncodingOptions($encodingOptions) {
        $this->encodingOptions=(int)$encodingOptions;
        return $this->setData(json_decode($this->data));
    }

    protected function update():JsonResponse {
        if (null !== $this->callback) {
            $message=$this->withHeader('Content-Type', 'text/javascript');
            return $message->setContent(sprintf('/**/%s(%s);', $this->callback, $this->data));
        }
        $message=null;
        if (!$this->hasHeader('Content-Type') || 'text/javascript' === $this->getHeader('Content-Type')) {
            $message=$this->withHeader('Content-Type', 'application/json');
        }
        return null!==$message?$message->setContent($message->data):$this->setContent($this->data);
    }
}