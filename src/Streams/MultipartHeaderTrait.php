<?php
namespace Lubed\Http\Streams;

trait MultipartHeaderTrait
{
    protected $headers = [];

    private function normalizeHeader(string $name): string
    {
        $name = strtolower($name);
        $name = explode('-', $name);
        $name = array_map(function ($word) {
            return ucfirst($word);
        }, $name);

        return implode('-', $name);
    }

    public function withHeader(string $name, $value)
    {
        $name = $this->normalizeHeader($name);
        $value = (string) $value;

        $this->headers[$name] = [$value];

        return $this;
    }

    public function withoutHeader(string $name)
    {
        $name = $this->normalizeHeader($name);
        unset($this->headers[$name]);

        return $this;
    }

    public function withAddedHeader(string $name, $value)
    {
        $name = $this->normalizeHeader($name);

        if (!$this->hasHeader($name)) {
            return $this->withHeader($name, $value);
        }

        $this->headers[$name][] = $value;

        return $this;
    }

    public function hasHeader(string $name): bool
    {
        $name = $this->normalizeHeader($name);

        return isset($this->headers[$name]);
    }

    public function getHeader(string $name): array
    {
        if (!$this->hasHeader($name)) {
            return [];
        }

        $name = $this->normalizeHeader($name);

        return $this->headers[$name];
    }

    public function getHeaderLine(string $name): string
    {
        if (!$this->hasHeader($name)) {
            return '';
        }

        return implode(',', $this->getHeader($name));
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
