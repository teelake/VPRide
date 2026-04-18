<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

final class SosNotifier
{
    /**
     * @param array<string, mixed> $incident
     * @param array<string, mixed> $ride
     */
    public static function emailOps(PDO $pdo, array $incident, array $ride, string $reporterEmail): void
    {
        $eff = AppSettingsRepository::emailOutboundEffective($pdo);
        $raw = trim((string) ($eff['sosNotifyEmails'] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($eff['staffNotifyEmails'] ?? ''));
        }
        if ($raw === '') {
            return;
        }
        $emails = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $raw) ?: [])));
        $from = $eff['mailFrom'] !== '' ? $eff['mailFrom'] : null;
        $subject = 'VP Ride SOS · Ride #' . (int) ($ride['id'] ?? 0);
        $adminSosUrl = Config::absoluteUrl('/admin/sos');
        $map = 'https://www.google.com/maps?q=' . urlencode(
            (string) ($incident['latitude'] ?? '') . ',' . (string) ($incident['longitude'] ?? ''),
        );
        $body = "SOS incident #" . (int) ($incident['id'] ?? 0) . "\n\n"
            . 'Ride ID: ' . (int) ($ride['id'] ?? 0) . "\n"
            . 'Ride status: ' . (string) ($ride['status'] ?? '') . "\n"
            . 'Reporter role: ' . (string) ($incident['reporter_role'] ?? '') . "\n"
            . 'Reporter user ID: ' . (int) ($incident['reporter_rider_user_id'] ?? 0) . "\n"
            . 'Reporter email: ' . $reporterEmail . "\n"
            . 'Location: ' . (string) ($incident['latitude'] ?? '') . ', ' . (string) ($incident['longitude'] ?? '') . "\n"
            . 'Map: ' . $map . "\n";
        if ($adminSosUrl !== '') {
            $body .= 'Admin console: ' . $adminSosUrl . "\n";
        }
        $msg = (string) ($incident['message'] ?? '');
        if ($msg !== '') {
            $body .= "\nMessage: {$msg}\n";
        }
        foreach ($emails as $to) {
            if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                Mailer::sendPlain($to, $subject, $body, $from);
            }
        }
    }
}
