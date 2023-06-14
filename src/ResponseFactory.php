<?php
namespace Lubed\Http;

class ResponseFactory
{
    public function make($content = '', $status = 200, string $reason='',array $options=[]):Response
    {
        $headers=$options['headers']??[];
        $version=$options['version']??'1.1';
        return new Response($content, $status, $reason,$headers,$version);
    }


    public function json($data = [], $status = 200, array $headers = [], $options = 0):JsonResponse
    {
        return new JsonResponse($data, $status, $headers, $options);
    }
}

