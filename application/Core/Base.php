<?php

declare(strict_types=1);

namespace Core;


class Base
{
    /**
     * Application version
     */
    protected const VERSION = '1.1.0';

    /**
     * Application path root
     */
    protected const PATH_ROOT = __DIR__ . '/../..';

    /**
     * Application folder name
     */
    protected const PATH_APPLICATION = 'application';

    /**
     * Controller folder name
     */
    protected const PATH_CONTROLLERS = 'Controllers';

    /**
     * Model folder name
     */
    protected const PATH_MODEL = 'Models';

    /**
     * Cache folder name
     */
    protected const PATH_CACHE = 'Cache';

    /**
     * Views folder name
     */
    protected const PATH_VIEWS = 'Views';

    /**
     * Tests folder name
     */
    protected const PATH_TESTS = 'Tests';

    /**
     * Get model
     * @var object
     */
    protected object $model;

    public function __construct()
    {
        $this->model = new Model();
    }
}