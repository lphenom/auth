<?php

declare(strict_types=1);

namespace LPhenom\Auth\Contracts;

/**
 * Interface for an authenticated user.
 *
 * The application's User model must implement this interface.
 *
 * KPHP-compatible: no union types (all IDs are string), no reflection.
 */
interface AuthenticatedUserInterface
{
    /**
     * Get the unique identifier for the user (e.g. primary key as string).
     */
    public function getAuthIdentifier(): string;

    /**
     * Get the user's roles as an array of strings.
     *
     * @return string[]
     */
    public function getAuthRoles(): array;

    /**
     * Get the hashed password, or null if password auth is not used.
     */
    public function getAuthPasswordHash(): ?string;

    /**
     * Whether the user account is active.
     */
    public function isActive(): bool;
}

