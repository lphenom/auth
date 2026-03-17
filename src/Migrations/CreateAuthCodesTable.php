<?php

declare(strict_types=1);

namespace LPhenom\Auth\Migrations;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Migration\MigrationInterface;

/**
 * Migration: create auth_codes table for SMS/email one-time codes.
 *
 * KPHP-compatible: no reflection, raw SQL.
  * @lphenom-build shared,kphp
 */
final class CreateAuthCodesTable implements MigrationInterface
{
    public function up(ConnectionInterface $conn): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS auth_codes ('
             . ' id INTEGER PRIMARY KEY AUTO_INCREMENT,'
             . ' channel VARCHAR(16) NOT NULL,'
             . ' recipient VARCHAR(255) NOT NULL,'
             . ' code_hash VARCHAR(64) NOT NULL,'
             . ' expires_at DATETIME NOT NULL,'
             . ' used_at DATETIME DEFAULT NULL,'
             . ' created_at DATETIME NOT NULL,'
             . ' KEY idx_recipient (recipient),'
             . ' KEY idx_expires_at (expires_at)'
             . ')';

        $conn->execute($sql);
    }

    public function down(ConnectionInterface $conn): void
    {
        $conn->execute('DROP TABLE IF EXISTS auth_codes');
    }

    public function getVersion(): string
    {
        return '20260317000002';
    }
}
