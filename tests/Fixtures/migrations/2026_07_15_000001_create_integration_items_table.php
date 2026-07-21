<?php

declare(strict_types=1);

use WpsMicro\Core\Migration;

return new class extends Migration {
    public function up(\PDO $db): void
    {
        $primaryKey = $db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';

        $db->exec(
            'CREATE TABLE integration_items (
                id ' . $primaryKey . ',
                name VARCHAR(255) NOT NULL
            )'
        );
    }

    public function down(\PDO $db): void
    {
        $db->exec('DROP TABLE integration_items');
    }
};
