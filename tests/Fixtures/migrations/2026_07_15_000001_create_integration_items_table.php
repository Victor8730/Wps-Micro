<?php

declare(strict_types=1);

use WpsMicro\Core\Migration;

return new class extends Migration {
    public function up(\PDO $db): void
    {
        $db->exec(
            'CREATE TABLE integration_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL
            )'
        );
    }

    public function down(\PDO $db): void
    {
        $db->exec('DROP TABLE integration_items');
    }
};
