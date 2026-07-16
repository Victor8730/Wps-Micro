<?php

declare(strict_types=1);

use Core\Migration;

return new class extends Migration {
    /**
     * Apply the migration.
     */
    public function up(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )'
        );
    }

    /**
     * Roll back the migration.
     */
    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS users');
    }
};
