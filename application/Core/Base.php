<?php

declare(strict_types=1);

namespace Core;


class Base implements Face\Base
{
    /**
     * Current framework version.
     */
    public const VERSION = '1.0.0';

    /**
     * Project root path.
     */
    public const PATH_ROOT = __DIR__ . '/../..';

    /**
     * Application directory name.
     */
    public const PATH_APPLICATION = 'application';

    /**
     * Controller directory name.
     */
    public const PATH_CONTROLLERS = 'Controllers';

    /**
     * Model directory name.
     */
    public const PATH_MODEL = 'Models';

    /**
     * Cache directory name.
     */
    public const PATH_CACHE = 'Cache';

    /**
     * View directory name.
     */
    public const PATH_VIEWS = 'Views';

    /**
     * Test directory name.
     */
    protected const PATH_TESTS = 'Tests';

    /**
     * Initialize shared framework state.
     */
    public function __construct()
    {

    }
}
