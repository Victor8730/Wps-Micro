<?php

declare(strict_types=1);

namespace Core;


class Base implements face\Base
{
    /**
     * Application version
     */
    public const VERSION = '1.1.0';

    /**
     * Application path root
     */
    public const PATH_ROOT = __DIR__ . '/../..';

    /**
     * Application folder name
     */
    public const PATH_APPLICATION = 'application';

    /**
     * Controller folder name
     */
    public const PATH_CONTROLLERS = 'Controllers';

    /**
     * Model folder name
     */
    public const PATH_MODEL = 'Models';

    /**
     * Cache folder name
     */
    public const PATH_CACHE = 'Cache';

    /**
     * Views folder name
     */
    public const PATH_VIEWS = 'Views';

    /**
     * Tests folder name
     */
    protected const PATH_TESTS = 'Tests';

    /**
     * Base constructor.
     */
    public function __construct()
    {

    }
}