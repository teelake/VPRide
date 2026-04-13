<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

final class RegionRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array<string, mixed>|null decoded payload for active config
     */
    public function getActivePayload(): ?array
    {
        $stmt = $this->pdo->query(
            'SELECT id, payload, updated_at FROM region_configs WHERE is_active = 1 ORDER BY id DESC LIMIT 1',
        );
        $row = $stmt->fetch();
        if (! $row) {
            return null;
        }
        $raw = $row['payload'];
        if (is_string($raw)) {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } elseif (is_array($raw)) {
            $data = $raw;
        } else {
            $data = json_decode(json_encode($raw, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        }
        $data['updatedAt'] = gmdate('Y-m-d\TH:i:s\Z', strtotime($row['updated_at']));

        return $data;
    }

    /**
     * @return list<array{id:int,label:string,is_active:int,updated_at:string}>
     */
    public function listConfigs(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, label, is_active, updated_at FROM region_configs ORDER BY id DESC',
        );

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array{id:int,label:string,payload:string,is_active:int}|null
     */
    public function getConfigRow(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, label, payload, is_active FROM region_configs WHERE id = ?',
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (! $row) {
            return null;
        }
        $raw = $row['payload'];
        $payload = is_string($raw)
            ? $raw
            : json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return [
            'id' => (int) $row['id'],
            'label' => (string) $row['label'],
            'payload' => $payload,
            'is_active' => (int) $row['is_active'],
        ];
    }

    /**
     * @throws \JsonException
     */
    public function updateConfig(int $id, string $label, string $jsonString, int $adminId): void
    {
        json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        $stmt = $this->pdo->prepare(
            'UPDATE region_configs SET label = ?, payload = ?, updated_by_admin_id = ? WHERE id = ?',
        );
        $stmt->execute([$label, $jsonString, $adminId, $id]);
    }

    public function createDraft(string $label, string $jsonString, int $adminId): int
    {
        json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        $stmt = $this->pdo->prepare(
            'INSERT INTO region_configs (label, payload, is_active, updated_by_admin_id) VALUES (?, ?, 0, ?)',
        );
        $stmt->execute([$label, $jsonString, $adminId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function activate(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('UPDATE region_configs SET is_active = 0');
            $stmt = $this->pdo->prepare('UPDATE region_configs SET is_active = 1 WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() !== 1) {
                throw new \RuntimeException('Config not found');
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
