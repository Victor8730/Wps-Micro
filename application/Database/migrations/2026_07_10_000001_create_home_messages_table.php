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
            'CREATE TABLE IF NOT EXISTS home_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $db->exec(
            "INSERT INTO home_messages (title, body)
            SELECT 'Hello from MariaDB', 'This message was loaded through the Home model.'
            WHERE NOT EXISTS (
                SELECT 1 FROM home_messages WHERE title = 'Hello from MariaDB'
            )"
        );
    }

    /**
     * Roll back the migration.
     */
    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS home_messages');
    }
};
