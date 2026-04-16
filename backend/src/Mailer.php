<?php

declare(strict_types=1);

namespace VprideBackend;

/**
 * Sends plain-text mail via PHP mail(). Configure APP_MAIL_FROM in .env (From header).
 */
final class Mailer
{
    /**
     * Replace {placeholder} tokens (e.g. {email}, {displayName}, {userId}, {greeting}).
     *
     * @param array<string, string|int|float> $vars
     */
    public static function expandTemplate(string $template, array $vars): string
    {
        $out = $template;
        foreach ($vars as $key => $value) {
            $out = str_replace('{' . $key . '}', (string) $value, $out);
        }

        return $out;
    }

    public static function sendPlain(string $to, string $subject, string $body, ?string $fromOverride = null): bool
    {
        $to = trim($to);
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $from = trim((string) ($fromOverride ?? ''));
        if ($from === '') {
            $from = trim((string) getenv('APP_MAIL_FROM'));
        }
        if ($from === '') {
            $from = 'VP Ride Console <noreply@localhost>';
        }
        $headers = [
            'From: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        return @mail($to, self::encodeSubject($subject), $body, implode("\r\n", $headers));
    }

    private static function encodeSubject(string $subject): string
    {
        if (preg_match('/[^\x20-\x7E]/', $subject)) {
            return '=?UTF-8?B?' . base64_encode($subject) . '?=';
        }

        return $subject;
    }
}
