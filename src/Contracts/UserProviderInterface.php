<?php

declare(strict_types=1);

namespace LPhenom\Auth\Contracts;

/**
 * Interface for retrieving users from the application's storage.
 *
 * The application must provide its own implementation (e.g. via DB query).
 *
 * KPHP-compatible: no reflection, no ORM.
  * @lphenom-build shared,kphp
 */
interface UserProviderInterface
{
    /**
     * Find a user by their unique identifier.
     */
    public function findById(string $id): ?AuthenticatedUserInterface;

    /**
     * Find a user by login (email, phone, username — depends on application).
     */
    public function findByLogin(string $login): ?AuthenticatedUserInterface;
}
