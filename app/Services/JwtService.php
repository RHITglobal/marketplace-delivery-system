<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class JwtService
{
    public function generateToken(User $user): string
    {
        $issuedAt = CarbonImmutable::now();
        $ttl = (int) env('JWT_TTL', 60);

        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'role' => $user->role,
            'iat' => $issuedAt->timestamp,
            'exp' => $issuedAt->addMinutes($ttl)->timestamp,
            'jti' => (string) Str::uuid(),
        ];

        return $this->encode($payload);
    }

    public function decodeToken(string $token): ?array
    {
        [$encodedHeader, $encodedPayload, $signature] = explode('.', $token) + [null, null, null];

        if (! $encodedHeader || ! $encodedPayload || ! $signature) {
            return null;
        }

        $expected = $this->sign("{$encodedHeader}.{$encodedPayload}");

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (! is_array($payload) || ! isset($payload['exp']) || CarbonImmutable::now()->timestamp >= (int) $payload['exp']) {
            return null;
        }

        return $payload;
    }

    private function encode(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $signature = $this->sign("{$encodedHeader}.{$encodedPayload}");

        return "{$encodedHeader}.{$encodedPayload}.{$signature}";
    }

    private function sign(string $value): string
    {
        return $this->base64UrlEncode(
            hash_hmac('sha256', $value, (string) env('JWT_SECRET', config('app.key')), true)
        );
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = 4 - (strlen($value) % 4);

        if ($padding < 4) {
            $value .= str_repeat('=', $padding);
        }

        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}
