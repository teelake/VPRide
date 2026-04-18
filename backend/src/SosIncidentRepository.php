<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;

final class SosIncidentRepository
{
    public function __construct(private PDO $pdo) {}

    public static function tableExists(PDO $pdo): bool
    {
        return SchemaInspector::tableExists($pdo, 'sos_incidents');
    }

    public function insert(
        int $rideId,
        int $reporterRiderUserId,
        string $reporterRole,
        float $lat,
        float $lng,
        ?float $accuracyM,
        ?string $message,
        ?string $clientRequestId,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sos_incidents (ride_id, reporter_rider_user_id, reporter_role, latitude, longitude, '
            . 'accuracy_m, message, client_request_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'open\')',
        );
        $stmt->execute([
            $rideId,
            $reporterRiderUserId,
            $reporterRole,
            round($lat, 7),
            round($lng, 7),
            $accuracyM !== null ? round($accuracyM, 2) : null,
            $message !== null && $message !== '' ? mb_substr($message, 0, 500) : null,
            $clientRequestId !== null && $clientRequestId !== '' ? $clientRequestId : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByStatus(string $status, int $limit = 200): array
    {
        $allowed = ['open', 'acknowledged', 'closed'];
        if (! in_array($status, $allowed, true)) {
            $status = 'open';
        }
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT s.*, r.status AS ride_status, r.rider_user_id, r.driver_rider_user_id, ru.email AS reporter_email '
            . 'FROM sos_incidents s '
            . 'INNER JOIN rides r ON r.id = s.ride_id '
            . 'INNER JOIN rider_users ru ON ru.id = s.reporter_rider_user_id '
            . 'WHERE s.status = ? ORDER BY s.id DESC LIMIT ?',
        );
        $stmt->bindValue(1, $status, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function acknowledge(int $id, int $adminId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE sos_incidents SET status = \'acknowledged\', acknowledged_at = NOW(), acknowledged_by_admin_id = ? '
            . 'WHERE id = ? AND status = \'open\'',
        );
        $stmt->execute([$adminId, $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Dedup: return existing id if same client_request_id already stored.
     */
    public function findIdByClientRequestId(string $uuid): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM sos_incidents WHERE client_request_id = ? LIMIT 1');
        $stmt->execute([$uuid]);
        $v = $stmt->fetchColumn();

        return $v !== false ? (int) $v : null;
    }
}
