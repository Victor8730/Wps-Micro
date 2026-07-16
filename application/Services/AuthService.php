<?php

declare(strict_types=1);

namespace Services;

use Core\Session;
use Models\User;
use PDOException;

class AuthService
{
    private User $users;

    private Session $session;

    private bool $userResolved = false;

    private ?array $currentUser = null;

    /**
     * Prepare the authentication service.
     */
    public function __construct(User $users, Session $session)
    {
        $this->users = $users;
        $this->session = $session;
    }

    /**
     * Authenticate a user with an email address and password.
     */
    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($this->normalizeEmail($email));

        if ($user === null || !password_verify($password, (string) $user['password'])) {
            return false;
        }

        if (password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $this->users->updatePasswordHash((int) $user['id'], $hash);
            $user['password'] = $hash;
        }

        $this->login($user);

        return true;
    }

    /**
     * Register and authenticate a new user.
     */
    public function register(string $name, string $email, string $password): ?array
    {
        $email = $this->normalizeEmail($email);

        if ($this->users->findByEmail($email) !== null) {
            return null;
        }

        try {
            $user = $this->users->create($name, $email, password_hash($password, PASSWORD_DEFAULT));
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return null;
            }

            throw $exception;
        }

        $this->login($user);

        return $this->withoutPassword($user);
    }

    /**
     * Return the authenticated user without its password hash.
     */
    public function user(): ?array
    {
        if ($this->userResolved) {
            return $this->currentUser;
        }

        $this->userResolved = true;
        $userId = $this->session->get('user_id');

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }

        $user = $this->users->findById((int) $userId);

        if ($user === null) {
            $this->session->forget('user_id');

            return null;
        }

        $this->currentUser = $this->withoutPassword($user);

        return $this->currentUser;
    }

    /**
     * Determine whether the current request has an authenticated user.
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * End the authenticated session.
     */
    public function logout(): void
    {
        $this->session->invalidate();
        $this->userResolved = true;
        $this->currentUser = null;
    }

    /**
     * Store an authenticated user in the session.
     */
    private function login(array $user): void
    {
        $this->session->regenerate();
        $this->session->set('user_id', (int) $user['id']);
        $this->userResolved = true;
        $this->currentUser = $this->withoutPassword($user);
    }

    /**
     * Normalize an email address before lookup or storage.
     */
    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Remove sensitive credentials from a user record.
     */
    private function withoutPassword(array $user): array
    {
        unset($user['password']);

        return $user;
    }
}
