<?php

declare(strict_types=1);

namespace LPhenom\Auth\Migrations;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Migration\MigrationInterface;

/**
 * Migration: create auth_tokens table.
 *
 * KPHP-compatible: no reflection, raw SQL.
 */
final class CreateAuthTokensTable implements MigrationInterface
{
    public function up(ConnectionInterface $conn): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS auth_tokens ('
             . ' id INTEGER PRIMARY KEY AUTO_INCREMENT,'
             . ' token_id VARCHAR(64) NOT NULL,'
             . ' user_id VARCHAR(255) NOT NULL,'
             . ' token_hash VARCHAR(64) NOT NULL,'
             . ' created_at DATETIME NOT NULL,'
             . ' expires_at DATETIME NOT NULL,'
             . ' revoked_at DATETIME DEFAULT NULL,'
             . ' meta_json TEXT DEFAULT NULL,'
             . ' UNIQUE KEY idx_token_id (token_id),'
             . ' KEY idx_user_id (user_id),'
             . ' KEY idx_expires_at (expires_at)'
             . ')';

        $conn->execute($sql);
    }

    public function down(ConnectionInterface $conn): void
    {
        $conn->execute('DROP TABLE IF EXISTS auth_tokens');
    }

    public function getVersion(): string
    {
        return '20260317000001';
    }
}

