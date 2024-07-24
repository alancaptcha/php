<?php

namespace AlanCaptcha\Php\Http;

interface HttpClient
{
    public function get(string $url, array $headers = []): array;
    public function post(string $url, string $body = null, array $headers = []): array;
    public function send(string $method, string $url, array $headers = [], string $body = null): array;
}
