<?php

declare(strict_types=1);

namespace WpsMicro\Core;

abstract class Migration
{
    /**
     * Apply the migration.
     */
    abstract public function up(\PDO $db): void;

    /**
     * Roll back the migration.
     */
    abstract public function down(\PDO $db): void;
}
