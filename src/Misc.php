<?php
namespace Lubed\Http;

class Misc
{
    public static function serializePsr7Headers(array $headers) : string
    {
        $message = '';

        foreach ($headers as $name => $values) {
            $message .= sprintf("%s: %s\n\r",$name,implode(', ', $values));
        }

        return $message;
    }

    public static function isRelativeUrl(string $url) : bool
    {
        //TODO
        $pattern = "/^(?:ftp|https?|feed)?:?\/\/(?:(?:(?:[\w\.\-\+!$&'\(\)*\+,;=]|%[0-9a-f]{2})+:)*
        (?:[\w\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})+@)?(?:
        (?:[a-z0-9\-\.]|%[0-9a-f]{2})+|(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\]))(?::[0-9]+)?(?:[\/|\?]
        (?:[\w#!:\.\?\+\|=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})*)?$/xi";

        return !preg_match($pattern, $url);
    }

    /**
     * @throws HttpException
     */
    public static function extractRelativeUrlComponents(string $url) : array
    {
        if (false === ($url = parse_url($url))) {
            Exceptions::InvalidArgument(sprintf('%s:%s%s',__CLASS__,'Malformed URL: ',$url));
        }

        return [$url['path'] ?? '/', $url['query'] ?? ''];
    }

    public static function convertSslOptionsKeys(array $options) : array
    {
        $keys = array_keys($options);
        $values = array_values($options);
        $keys = array_map(
            function ($key) {
                return preg_replace_callback(
                    '~[A-Z][a-z]~',
                    function ($matches) {
                        return '_'.strtolower($matches[0]);
                    },
                    $key
                );
            },
            $keys
        );

        return array_combine($keys, $values);
    }
}
