<?php

declare(strict_types=1);

namespace LPhenom\Auth\Support;

use LPhenom\Auth\Contracts\TokenRepositoryInterface;
use LPhenom\Auth\DTO\TokenRecord;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Param\Param;

/**
 * Database-backed token repository using lphenom/db.
 *
 * Stores token records in the `auth_tokens` table.
 *
 * KPHP-compatible: no reflection, no ORM, raw SQL only.
 */
final class DbTokenRepository implements TokenRepositoryInterface
{
    /** @var ConnectionInterface */
    private ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function create(TokenRecord $token): void
    {
        $sql = 'INSERT INTO auth_tokens (token_id, user_id, token_hash, created_at, expires_at, revoked_at, meta_json)'
             . ' VALUES (:token_id, :user_id, :token_hash, :created_at, :expires_at, :revoked_at, :meta_json)';

        /** @var array<string, Param> $params */
        $params = [];
        $params['token_id']   = new Param($token->tokenId, 2);
        $params['user_id']    = new Param($token->userId, 2);
        $params['token_hash'] = new Param($token->tokenHash, 2);
        $params['created_at'] = new Param($token->createdAt, 2);
        $params['expires_at'] = new Param($token->expiresAt, 2);

        if ($token->revokedAt !== null) {
            $params['revoked_at'] = new Param($token->revokedAt, 2);
        } else {
            $params['revoked_at'] = new Param('', 0, true);
        }

        if ($token->metaJson !== '') {
            $params['meta_json'] = new Param($token->metaJson, 2);
        } else {
            $params['meta_json'] = new Param('', 0, true);
        }

        $this->db->execute($sql, $params);
    }

    public function findByTokenId(string $tokenId): ?TokenRecord
    {
        $sql = 'SELECT token_id, user_id, token_hash, created_at, expires_at, revoked_at, meta_json'
             . ' FROM auth_tokens WHERE token_id = :token_id LIMIT 1';

        /** @var array<string, Param> $params */
        $params = [];
        $params['token_id'] = new Param($tokenId, 2);

        $result = $this->db->query($sql, $params);
        $row = $result->fetchOne();
        if ($row === null) {
            return null;
        }

        return $this->hydrateRow($row);
    }

    public function revoke(string $tokenId): void
    {
        $now = new \DateTimeImmutable();
        $sql = 'UPDATE auth_tokens SET revoked_at = :revoked_at WHERE token_id = :token_id';

        /** @var array<string, Param> $params */
        $params = [];
        $params['revoked_at'] = new Param($now->format('Y-m-d H:i:s'), 2);
        $params['token_id']   = new Param($tokenId, 2);

        $this->db->execute($sql, $params);
    }

    public function revokeAllForUser(string $userId): void
    {
        $now = new \DateTimeImmutable();
        $sql = 'UPDATE auth_tokens SET revoked_at = :revoked_at WHERE user_id = :user_id AND revoked_at IS NULL';

        /** @var array<string, Param> $params */
        $params = [];
        $params['revoked_at'] = new Param($now->format('Y-m-d H:i:s'), 2);
        $params['user_id']    = new Param($userId, 2);

        $this->db->execute($sql, $params);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRow(array $row): TokenRecord
    {
        $tokenId   = isset($row['token_id']) && is_string($row['token_id']) ? $row['token_id'] : '';
        $userId    = isset($row['user_id']) && is_string($row['user_id']) ? $row['user_id'] : '';
        $tokenHash = isset($row['token_hash']) && is_string($row['token_hash']) ? $row['token_hash'] : '';
        $createdAt = isset($row['created_at']) && is_string($row['created_at']) ? $row['created_at'] : '';
        $expiresAt = isset($row['expires_at']) && is_string($row['expires_at']) ? $row['expires_at'] : '';

        $revokedAt = null;
        if (isset($row['revoked_at']) && is_string($row['revoked_at'])) {
            $revokedAt = $row['revoked_at'];
        }

        $metaJson = '';
        if (isset($row['meta_json']) && is_string($row['meta_json'])) {
            $metaJson = $row['meta_json'];
        }

        return new TokenRecord(
            $tokenId,
            $userId,
            $tokenHash,
            $createdAt,
            $expiresAt,
            $revokedAt,
            $metaJson
        );
    }
}

