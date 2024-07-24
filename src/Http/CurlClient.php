<?php

namespace AlanCaptcha\Php\Http;

/**
 * Curl based http client
 */
class CurlClient implements HttpClient
{
    public function get(string $url, array $headers = []): array
    {
        return $this->send('GET', $url,  $headers);
    }

    public function post(string $url, string $body = null, array $headers = []): array
    {
        return $this->send('POST', $url, $headers, $body);
    }

    public function send(string $method, string $url,  array $headers = [], string $body = null): array
    {
        $ch = curl_init();

        // Set cURL options common for all HTTP methods
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        // Set method-specific options
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif (strtoupper($method) !== 'GET') {
            // Handle other methods here if needed (PUT, DELETE, etc.)
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // Set headers if provided
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // Execute the request
        $response = curl_exec($ch);

        // Separate header and body
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        // Get status code
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Get content length
        $content_length = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        // Close cURL session
        curl_close($ch);

        // Parse headers into an associative array
        $header_array = [];
        $response_headers = explode("\r\n", $header);
        foreach ($response_headers  as $header_line) {
            if (strpos($header_line, ':') !== false) {
                list($key, $value) = explode(': ', $header_line, 2);
                $header_array[\strtolower($key)] = $value;
            }
        }
        if (isset($header_array['content-type']) && strcmp($header_array['content-type'], 'application/json') == 0) {
            $decoded_body = json_decode($body, true);

            // Check if json_decode failed
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded_body = $body; // Keep the original string if decoding failed
            }
        }

        // Return the array as specified
        return [
            'header' => $header_array,
            'body' => $decoded_body,
            'meta' => [
                'status' => (string)$http_status,
                'content_length' => (string)$content_length
            ]
        ];
    }
}
