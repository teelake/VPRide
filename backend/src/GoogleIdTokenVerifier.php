<?php

declare(strict_types=1);

namespace VprideBackend;

use DomainException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use RuntimeException;
use UnexpectedValueException;

/**
 * Verifies Google Sign-In ID tokens (RS256 + Google JWKS).
 * Requires firebase/php-jwt (composer) and the Google Sign-In *server* client ID (same value as
 * Flutter `serverClientId`; in Cloud Console it is often created as OAuth type "Web application").
 */
final class GoogleIdTokenVerifier
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    /**
     * @return object{sub: string, email?: string, email_verified?: bool, name?: string, picture?: string}
     */
    public static function verify(string $idToken, string $expectedAudience): object
    {
        $clientId = trim($expectedAudience);
        if ($clientId === '') {
            throw new RuntimeException('Google Sign-In server client ID is not configured (set GOOGLE_OAUTH_CLIENT_ID or app_public_settings.googleWebClientId)');
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $raw = @file_get_contents(self::JWKS_URL, false, $ctx);
        if (! is_string($raw) || $raw === '') {
            throw new RuntimeException('Could not fetch Google JWKS');
        }
        /** @var array<string, mixed> $set */
        $set = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $keys = JWK::parseKeySet($set);

        try {
            $payload = JWT::decode($idToken, $keys);
        } catch (UnexpectedValueException | DomainException $e) {
            throw new RuntimeException('Invalid ID token: ' . $e->getMessage(), 0, $e);
        }

        $iss = $payload->iss ?? '';
        if ($iss !== 'https://accounts.google.com' && $iss !== 'accounts.google.com') {
            throw new RuntimeException('Invalid token issuer');
        }

        $aud = $payload->aud ?? null;
        $audOk = $aud === $clientId
            || (is_array($aud) && in_array($clientId, $aud, true));
        if (! $audOk) {
            throw new RuntimeException('Invalid token audience');
        }

        $sub = $payload->sub ?? '';
        if (! is_string($sub) || $sub === '') {
            throw new RuntimeException('Token missing subject');
        }

        return $payload;
    }
}
