<?php

declare(strict_types=1);

namespace Core;

class Model extends Base
{
    /**
     * Database connection used by concrete models.
     */
    protected \PDO $db;

    /**
     * Prepare the model database connection.
     */
    public function __construct(\PDO $db)
    {
        $this->db = $db;

        parent::__construct();
    }
}
