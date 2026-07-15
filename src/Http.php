<?php

declare(strict_types=1);

namespace ForceDream;

/**
 * Minimal, dependency-free HTTP helper using PHP's built-in curl extension (universally
 * available -- no Guzzle or other Composer HTTP client required, keeping this SDK's real
 * dependency footprint at zero beyond PHP core + the bundled sodium extension).
 */
final class Http
{
    /** @return array{status: int, json: mixed} */
    public static function get(string $url, ?string $apiKey = null): array
    {
        $headers = [];
        if ($apiKey !== null) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        return self::request('GET', $url, null, $headers);
    }

    /** @return array{status: int, json: mixed} */
    public static function post(string $url, mixed $body, ?string $apiKey = null): array
    {
        $headers = ['Content-Type: application/json'];
        if ($apiKey !== null) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        return self::request('POST', $url, json_encode($body ?? new \stdClass()), $headers);
    }

    /** @return array{status: int, json: mixed} */
    private static function request(string $method, string $url, ?string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            throw new \RuntimeException("HTTP $method $url failed: $err");
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $json = null;
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            $json = $decoded;
        }
        return ['status' => $status, 'json' => $json];
    }
}
