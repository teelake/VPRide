<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;

/**
 * Dashboard metrics — queries are defensive (empty arrays / zeros if tables missing).
 */
final class AnalyticsRepository
{
    public function __construct(private PDO $pdo) {}

    public function ridesCountLastHours(int $hours): int
    {
        $hours = max(1, min(168, $hours));
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM rides WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)',
            );
            $stmt->execute([$hours]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException) {
            return 0;
        }
    }

    public function ridersNewLastDays(int $days): int
    {
        return $this->countSinceDays('rider_users', $days);
    }

    public function ridesNewLastDays(int $days): int
    {
        return $this->countSinceDays('rides', $days);
    }

    private function countSinceDays(string $table, int $days): int
    {
        if ($table !== 'rides' && $table !== 'rider_users') {
            return 0;
        }
        $days = max(1, min(90, $days));
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)",
            );
            $stmt->execute([$days]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException) {
            return 0;
        }
    }

    /**
     * @return list<array{status: string, c: int}>
     */
    public function ridesByStatus(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT status, COUNT(*) AS c FROM rides GROUP BY status ORDER BY c DESC',
            );

            return $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return [];
        }
    }

    /**
     * @return list<array{d: string, c: int}>  d = Y-m-d
     */
    public function ridesPerDayLastDays(int $days): array
    {
        return $this->dailyCounts('rides', $days);
    }

    /**
     * @return list<array{d: string, c: int}>
     */
    public function ridersPerDayLastDays(int $days): array
    {
        return $this->dailyCounts('rider_users', $days);
    }

    /**
     * @return list<array{d: string, c: int}>
     */
    private function dailyCounts(string $table, int $days): array
    {
        if ($table !== 'rides' && $table !== 'rider_users') {
            return [];
        }
        $col = 'created_at';
        $days = max(1, min(90, $days));
        try {
            $sql = "SELECT DATE({$col}) AS d, COUNT(*) AS c FROM {$table} "
                . "WHERE {$col} >= DATE_SUB(CURDATE(), INTERVAL ? DAY) "
                . "GROUP BY DATE({$col}) ORDER BY d ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$days]);
            $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return [];
        }
        $byDay = [];
        foreach ($raw as $row) {
            $byDay[(string) $row['d']] = (int) $row['c'];
        }
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime('-' . $i . ' days'));
            $out[] = ['d' => $d, 'c' => $byDay[$d] ?? 0];
        }

        return $out;
    }
}
