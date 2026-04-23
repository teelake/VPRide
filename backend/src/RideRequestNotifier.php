<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

/**
 * Notifies console staff by email when a new ride is requested (app or manual booking).
 */
final class RideRequestNotifier
{
    public static function notifyIfEnabled(PDO $pdo, int $rideId): void
    {
        $repo = new AppSettingsRepository($pdo);
        $email = $repo->getEmailSettings();
        if (empty($email['notifyOnNewRide'])) {
            return;
        }
        $toList = trim((string) ($email['newRideNotifyEmails'] ?? ''));
        if ($toList === '') {
            $toList = trim((string) ($email['staffNotifyEmails'] ?? ''));
        }
        if ($toList === '') {
            $toList = trim((string) getenv('RIDER_SIGNUP_NOTIFY_EMAIL'));
        }
        if ($toList === '') {
            return;
        }

        $ride = (new RideRepository($pdo))->findById($rideId);
        if ($ride === null) {
            return;
        }
        $riderId = (int) ($ride['rider_user_id'] ?? 0);
        $riderRow = $pdo->prepare('SELECT email, display_name FROM rider_users WHERE id = ? LIMIT 1');
        $riderRow->execute([$riderId]);
        $rInfo = $riderRow->fetch(PDO::FETCH_ASSOC);
        $riderEmail = $rInfo !== false ? trim((string) ($rInfo['email'] ?? '')) : '';
        $riderName = $rInfo !== false ? trim((string) ($rInfo['display_name'] ?? '')) : '';

        $pick = trim((string) ($ride['pickup_address'] ?? ''));
        if ($pick === '' && isset($ride['pickup_lat'], $ride['pickup_lng'])) {
            $pick = (string) $ride['pickup_lat'] . ', ' . (string) $ride['pickup_lng'];
        }
        $drop = trim((string) ($ride['dropoff_address'] ?? ''));
        if ($drop === '' && isset($ride['dropoff_lat'], $ride['dropoff_lng'])
            && $ride['dropoff_lat'] !== null && $ride['dropoff_lng'] !== null) {
            $drop = (string) $ride['dropoff_lat'] . ', ' . (string) $ride['dropoff_lng'];
        }
        if ($drop === '' || $drop === '0.0000000, 0.0000000') {
            $drop = '(not set)';
        }

        $eff = AppSettingsRepository::emailOutboundEffective($pdo);
        $from = $eff['mailFrom'] !== '' ? $eff['mailFrom'] : null;

        $subjT = (string) ($email['newRideNotifySubject'] ?? 'VP Ride: new ride request');
        $bodyT = (string) ($email['newRideNotifyBody'] ?? '');

        $consoleUrl = Config::absoluteUrl('/rides/' . $rideId . '/dispatch');

        $vars = [
            'rideId' => $rideId,
            'status' => (string) ($ride['status'] ?? ''),
            'riderUserId' => $riderId,
            'riderEmail' => $riderEmail,
            'riderName' => $riderName,
            'pickupLine' => $pick !== '' ? $pick : '—',
            'dropoffLine' => $drop,
            'consoleUrl' => $consoleUrl,
        ];
        $subject = Mailer::expandTemplate($subjT, $vars);
        $body = Mailer::expandTemplate($bodyT, $vars);
        if ($body === '' || $subject === '') {
            return;
        }

        foreach (preg_split('/[,\s;]+/', $toList, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $one) {
            $one = trim($one);
            if ($one !== '' && filter_var($one, FILTER_VALIDATE_EMAIL)) {
                Mailer::sendPlain($one, $subject, $body, $from);
            }
        }
    }
}
