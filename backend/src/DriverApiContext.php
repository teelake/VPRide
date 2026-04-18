<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

/**
 * Shared bearer auth + active fleet driver gate for /api/v1/driver/* handlers.
 */
final class DriverApiContext
{
    /**
     * @return array{riderUserId: int, fleet: array<string, mixed>}|null
     *         null if unauthorized JSON already sent
     */
    public static function requireFleetDriver(PDO $pdo): ?array
    {
        $token = RiderAuthService::readBearerFromRequest();
        if ($token === null || $token === '') {
            http_response_code(401);
            echo json_encode(['error' => 'unauthorized'], JSON_THROW_ON_ERROR);
            exit;
        }
        $auth = new RiderAuthService($pdo);
        $user = $auth->resolveBearerToken($token);
        if ($user === null) {
            http_response_code(401);
            echo json_encode(['error' => 'invalid_session'], JSON_THROW_ON_ERROR);
            exit;
        }
        $fleet = (new DriverFleetRepository($pdo))->findActiveFleetRowForRiderUser($user['rider_user_id']);
        if ($fleet === null) {
            http_response_code(403);
            echo json_encode(['error' => 'not_a_driver'], JSON_THROW_ON_ERROR);
            exit;
        }

        return [
            'riderUserId' => $user['rider_user_id'],
            'fleet' => $fleet,
        ];
    }
}
