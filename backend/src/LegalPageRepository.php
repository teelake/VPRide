<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;
use RuntimeException;

final class LegalPageRepository
{
    public const SLUG_TERMS = 'terms_of_use';

    public const SLUG_PRIVACY = 'privacy_policy';

    /** @return list<string> */
    public static function allowedSlugs(): array
    {
        return [self::SLUG_TERMS, self::SLUG_PRIVACY];
    }

    public function __construct(private PDO $pdo) {}

    /**
     * @return array{slug: string, title: string, body_html: string, updated_at: string, updated_by_admin_id: int|null}|null
     */
    public function getBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if (! in_array($slug, self::allowedSlugs(), true)) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT slug, title, body_html, updated_at, updated_by_admin_id '
            . 'FROM rider_legal_pages WHERE slug = ? LIMIT 1',
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (! $row) {
            return null;
        }

        return [
            'slug' => (string) $row['slug'],
            'title' => (string) $row['title'],
            'body_html' => (string) $row['body_html'],
            'updated_at' => (string) $row['updated_at'],
            'updated_by_admin_id' => isset($row['updated_by_admin_id']) && $row['updated_by_admin_id'] !== null
                ? (int) $row['updated_by_admin_id']
                : null,
        ];
    }

    /**
     * @return list<array{slug: string, title: string, body_html: string, updated_at: string, updated_by_admin_id: int|null}>
     */
    public function listAll(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT slug, title, body_html, updated_at, updated_by_admin_id '
                . 'FROM rider_legal_pages ORDER BY slug ASC',
            );
        } catch (PDOException) {
            return [];
        }
        if (! $stmt) {
            return [];
        }
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'slug' => (string) $row['slug'],
                'title' => (string) $row['title'],
                'body_html' => (string) $row['body_html'],
                'updated_at' => (string) $row['updated_at'],
                'updated_by_admin_id' => isset($row['updated_by_admin_id']) && $row['updated_by_admin_id'] !== null
                    ? (int) $row['updated_by_admin_id']
                    : null,
            ];
        }

        return $out;
    }

    public function upsert(string $slug, string $title, string $bodyHtml, int $updatedByAdminId): void
    {
        if (! in_array($slug, self::allowedSlugs(), true)) {
            throw new RuntimeException('Invalid legal page slug');
        }
        $title = trim($title);
        if ($title === '') {
            throw new RuntimeException('Title is required');
        }
        if (strlen($title) > 255) {
            throw new RuntimeException('Title is too long');
        }
        $bodyHtml = self::sanitizeLegalHtml($bodyHtml);
        if (strlen($bodyHtml) > 500_000) {
            throw new RuntimeException('Content is too long');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO rider_legal_pages (slug, title, body_html, updated_by_admin_id) VALUES (?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE title = VALUES(title), body_html = VALUES(body_html), '
            . 'updated_by_admin_id = VALUES(updated_by_admin_id)',
        );
        $stmt->execute([$slug, $title, $bodyHtml, $updatedByAdminId]);
    }

    /**
     * Strip risky tags/attributes while keeping typical Quill output.
     */
    public static function sanitizeLegalHtml(string $html): string
    {
        $html = str_replace("\0", '', $html);
        $allowed =
            '<p><br><strong><b><em><i><u><s><strike><sub><sup><blockquote><pre><code>'
            . '<h1><h2><h3><h4><h5><h6><ol><ul><li><a><span><div><hr>';
        $clean = strip_tags($html, $allowed);

        return trim($clean);
    }
}
