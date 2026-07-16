<?php

declare(strict_types=1);

namespace Models;

use Core\Model;
use RuntimeException;

class User extends Model
{
    /**
     * Find a user by its primary key.
     */
    public function findById(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, name, email, password, created_at, updated_at FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    /**
     * Find a user by email address.
     */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, name, email, password, created_at, updated_at FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);

        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    /**
     * Store a new user and return it.
     */
    public function create(string $name, string $email, string $passwordHash): array
    {
        $statement = $this->db->prepare(
            'INSERT INTO users (name, email, password) VALUES (:name, :email, :password)'
        );
        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password' => $passwordHash,
        ]);

        $user = $this->findById((int) $this->db->lastInsertId());

        if ($user === null) {
            throw new RuntimeException('The newly created user could not be loaded.');
        }

        return $user;
    }

    /**
     * Replace a user's password hash.
     */
    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $statement = $this->db->prepare('UPDATE users SET password = :password WHERE id = :id');
        $statement->execute([
            'id' => $id,
            'password' => $passwordHash,
        ]);
    }
}
