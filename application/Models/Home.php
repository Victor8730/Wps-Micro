<?php

declare(strict_types=1);

namespace Models;

use Core\Model;

class Home extends Model
{
    /**
     * Return recent home page messages from storage.
     */
    public function latestMessages(int $limit = 5): array
    {
        $statement = $this->db->prepare(
            'SELECT id, title, body, created_at FROM home_messages ORDER BY id DESC LIMIT :limit'
        );
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
