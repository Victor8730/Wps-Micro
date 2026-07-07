<?php

declare(strict_types=1);

namespace Core;

use Exceptions\{NotExistClassException, NotExistMethodException, NotExistFileException};

class Route
{
    /**
     * Default controller name.
     */
    private const DEFAULT_CONTROLLER = 'home';

    /**
     * Default action name.
     */
    private const DEFAULT_ACTION = 'index';

    /**
     * Controller name.
     */
    private string $controllerName;

    /**
     * Action name.
     */
    private string $actionName;

    /**
     * Model instance used for shared services.
     */
    private Model $model;

    /**
     * Prepare default route values.
     */
    public function __construct()
    {
        $this->controllerName = self::DEFAULT_CONTROLLER;
        $this->actionName = self::DEFAULT_ACTION;
        $this->model = new Model();
    }

    /**
     * Resolve the current request and execute the matching controller action.
     */
    public function initialize(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (!empty($segments)) {
            $this->controllerName = $segments[0];
            $this->actionName = $segments[1] ?? self::DEFAULT_ACTION;
        }

        if (!$this->isValidRouteSegment($this->controllerName) || !$this->isValidRouteSegment($this->actionName)) {
            self::errorPage404();
        }

        $controllerName = Base::PATH_CONTROLLERS . '\Controller' . ucfirst($this->controllerName);
        $controllerPath = Base::PATH_ROOT . '/'
            . Base::PATH_APPLICATION . '/'
            . Base::PATH_CONTROLLERS . '/Controller'
            . ucfirst($this->controllerName) . '.php';

        try {
            $this->model->validator->checkFileExist($controllerPath);
            $this->model->validator->checkClassExist($controllerName);
            $controller = new $controllerName;
        } catch (NotExistClassException $e) {
            self::errorPage404();
        } catch (NotExistFileException $e) {
            self::errorPage404();
        }

        $actionName = 'action' . ucfirst($this->actionName);

        try {
            $this->model->validator->checkMethodExist($controller, $actionName);
            $controller->$actionName();
        } catch (NotExistMethodException $e) {
            self::errorPage404();
        }
    }

    /**
     * Render the 404 response and stop request processing.
     */
    public static function errorPage404(): void
    {
        http_response_code(404);

        try {
            (new \Controllers\Controller404())->actionIndex();
        } catch (\Throwable $e) {
            echo 'Page not found';
        }

        exit;
    }

    /**
     * Check whether a route segment is safe to use as a class or method name.
     */
    private function isValidRouteSegment(string $segment): bool
    {
        return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $segment);
    }
}
