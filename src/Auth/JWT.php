<?php
/**
 * JWT (JSON Web Token) Handler
 * Handles JWT token generation and validation
 */

namespace P2P\Auth;

use P2P\Core\Config;

class JWT
{
    private static string $secret;
    private static string $algorithm;
    private static int $expiry;

    /**
     * Initialize JWT configuration
     */
    public static function init(): void
    {
        self::$secret = Config::get('auth.jwt_secret', 'default-secret-key');
        self::$algorithm = Config::get('auth.jwt_algorithm', 'HS256');
        self::$expiry = Config::get('auth.jwt_expiry', 86400);
    }

    /**
     * Generate JWT token
     */
    public static function encode(array $payload): string
    {
        if (self::$secret === null) {
            self::init();
        }

        // Add standard claims
        $payload['iat'] = time();
        $payload['exp'] = time() + self::$expiry;

        // Encode header and payload
        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => self::$algorithm]));
        $payload = self::base64UrlEncode(json_encode($payload));

        // Generate signature
        $signature = self::base64UrlEncode(self::sign("{$header}.{$payload}"));

        return "{$header}.{$payload}.{$signature}";
    }

    /**
     * Decode and validate JWT token
     */
    public static function decode(string $token): ?array
    {
        if (self::$secret === null) {
            self::init();
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        // Verify signature
        $expectedSignature = self::base64UrlEncode(self::sign("{$header}.{$payload}"));

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Decode payload
        $decodedPayload = json_decode(self::base64UrlDecode($payload), true);

        // Check expiration
        if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
            return null;
        }

        return $decodedPayload;
    }

    /**
     * Generate signature
     */
    private static function sign(string $data): string
    {
        switch (self::$algorithm) {
            case 'HS256':
                return hash_hmac('sha256', $data, self::$secret, true);
            case 'HS384':
                return hash_hmac('sha384', $data, self::$secret, true);
            case 'HS512':
                return hash_hmac('sha512', $data, self::$secret, true);
            default:
                throw new \Exception("Unsupported algorithm: " . self::$algorithm);
        }
    }

    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Extract token from Authorization header
     */
    public static function extractFromHeader(?string $header): ?string
    {
        if (!$header) {
            return null;
        }

        if (preg_match('/Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
