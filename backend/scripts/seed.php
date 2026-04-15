#!/usr/bin/env php
<?php

/**
 * One-time seed: default system admin + active Canada region config.
 * Usage: php scripts/seed.php
 * Requires .env or environment variables (see ../.env.example).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/Config.php';
require_once $root . '/src/Database.php';

use VprideBackend\Config;
use VprideBackend\Database;

Config::load($root . '/.env');

$pdo = Database::pdo();
$email = getenv('SEED_ADMIN_EMAIL') ?: 'admin@vpride.local';
$plain = getenv('SEED_ADMIN_PASSWORD') ?: 'Admin@123';

$hash = password_hash($plain, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    fwrite(STDERR, "Admin already exists: {$email}\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $rid = (int) $pdo->query("SELECT id FROM admin_roles WHERE slug = 'system_admin' LIMIT 1")->fetchColumn();
    if ($rid < 1) {
        throw new \RuntimeException('admin_roles not seeded: import schema or migration first');
    }
    $pdo->prepare(
        'INSERT INTO admins (email, password_hash, role_id) VALUES (?, ?, ?)',
    )->execute([$email, $hash, $rid]);
    $adminId = (int) $pdo->lastInsertId();

    $payload = defaultRegionPayload();

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $pdo->prepare(
        'INSERT INTO region_configs (label, payload, is_active, updated_by_admin_id) VALUES (?, ?, 1, ?)',
    )->execute(['Default — Modern Canada', $json, $adminId]);

    $pdo->commit();
    echo "Seeded admin: {$email}\n";
    echo "Seeded password (change in production): {$plain}\n";
    echo "Region config row activated.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . "\n");
    exit(1);
}

/**
 * @return array<string, mixed>
 */
function defaultRegionPayload(): array
{
    return [
        'version' => 1,
        'updatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
        'branding' => [
            'serviceAreaLabel' => 'Modern Canada',
        ],
        'localization' => [
            'defaultLocale' => 'en_CA',
            'supportedLocales' => ['en_CA', 'fr_CA'],
        ],
        'countries' => [
            [
                'code' => 'CA',
                'name' => 'Canada',
                'currencyCode' => 'CAD',
                'distanceUnit' => 'km',
                'cities' => [
                    [
                        'id' => 'yyz',
                        'name' => 'Toronto',
                        'subdivision' => 'ON',
                        'isActive' => true,
                        'center' => ['latitude' => 43.6532, 'longitude' => -79.3832],
                    ],
                    [
                        'id' => 'yvr',
                        'name' => 'Vancouver',
                        'subdivision' => 'BC',
                        'isActive' => true,
                        'center' => ['latitude' => 49.2827, 'longitude' => -123.1207],
                    ],
                ],
            ],
        ],
        'defaults' => [
            'countryCode' => 'CA',
            'cityId' => 'yyz',
        ],
    ];
}
