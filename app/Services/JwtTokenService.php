<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtTokenService
{
    public function createAccessToken(array $user): string
    {
        $now = time();
        $ttlMinutes = (int) env('JWT_ACCESS_TTL_MINUTES', 30);

        $payload = [
            'iss' => config('app.url'),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + ($ttlMinutes * 60),
            'sub' => $user['uid'],
            'email' => $user['email'],
            'type' => 'access',
        ];

        return JWT::encode($payload, $this->secret(), 'HS256');
    }

    public function decodeAccessToken(string $token): object
    {
        return JWT::decode($token, new Key($this->secret(), 'HS256'));
    }

    public function createRefreshToken(): string
    {
        return bin2hex(random_bytes(64));
    }

    public function hashRefreshToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function secret(): string
    {
        $secret = env('JWT_SECRET');

        if (! $secret) {
            throw new \RuntimeException('JWT_SECRET is missing in .env file.');
        }

        return $secret;
    }
}