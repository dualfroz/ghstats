<?php

declare(strict_types=1);

namespace Ghapi\Support;

final class Http
{
    public static function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        int $timeout = 60,
    ): HttpResponse {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        if (!isset($headers['User-Agent'])) {
            $lines[] = 'User-Agent: ghapi/2';
        }
        $options = [
            'method' => $method,
            'header' => implode("\r\n", $lines),
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 5,
            'protocol_version' => 1.1,
        ];
        if ($body !== null) {
            $options['content'] = $body;
        }
        $context = stream_context_create(['http' => $options, 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $http_response_header = [];
        $raw = @file_get_contents($url, false, $context);
        return HttpResponse::fromStream($raw === false ? null : $raw, $http_response_header);
    }

    public static function download(string $url, array $headers, string $target, int $maxBytes, int $timeout = 600): HttpResponse
    {
        $lines = ['User-Agent: ghapi/2'];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $lines),
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 5,
        ]]);
        $source = @fopen($url, 'rb', false, $context);
        if ($source === false) {
            return HttpResponse::fromStream(null, []);
        }
        $meta = stream_get_meta_data($source);
        $response = HttpResponse::fromStream('', $meta['wrapper_data'] ?? []);
        if ($response->status !== 200) {
            $response = HttpResponse::fromStream((string) stream_get_contents($source, 65536), $meta['wrapper_data'] ?? []);
            fclose($source);
            return $response;
        }
        $sink = fopen($target, 'wb');
        $copied = 0;
        while (!feof($source)) {
            $chunk = fread($source, 1 << 20);
            if ($chunk === false) {
                break;
            }
            $copied += strlen($chunk);
            if ($copied > $maxBytes) {
                fclose($source);
                fclose($sink);
                @unlink($target);
                return new HttpResponse(413, $response->headers, 'download exceeded limit');
            }
            fwrite($sink, $chunk);
        }
        fclose($source);
        fclose($sink);
        return $response;
    }
}
