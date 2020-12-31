<?php

declare(strict_types=1);

namespace Core;

use Exceptions\{NotExistClassException, NotExistMethodException, NotExistFileException};

class Route
{
    /**
     * Controller Name
     * @var string
     */
    private string $controllerName;

    /**
     * Action Name
     * @var string
     */
    private string $actionName;

    /**
     * Create Model
     */
    private object $model;

    /**
     * Route constructor.
     * Fill in the required data, controller name and method name.
     * Create model object for get validator and other base model object
     */
    public function __construct()
    {
        $this->controllerName = 'home';
        $this->actionName = 'index';
        $this->model = new Model();
    }

    /**
     * Starting the router, get uri
     * Parse url if path is not empty
     * We check the presence of a controller and if it is not, we return 404
     */
    public function initialize(): void
    {
        $parseUrl = parse_url($_SERVER['REQUEST_URI']);

        if (!empty($parseUrl['path'])) {
            $routes = explode('/', $parseUrl['path']);

            if (!empty($parseUrl['query'])) {
                parse_str($parseUrl['query'], $queryUrl);
            }

            $arrRoutes = array_reverse(array_diff($routes, ['']));

            try {
                if (count($arrRoutes) > 0) {
                    $nameController = $arrRoutes[1] ?? $arrRoutes[0];
                    $this->model->validator->checkFileExist(BASE::PATH_APPLICATION . '/' . BASE::PATH_CONTROLLERS . '/Controller' . ucfirst($nameController) . '.php');

                    if (!empty($arrRoutes[1])) {
                        $this->actionName = $arrRoutes[0];
                    }

                    $this->controllerName = $nameController;
                }
            } catch (NotExistFileException $e) {
                self::errorPage404();
            }
        }

        $controllerName = BASE::PATH_CONTROLLERS . '\Controller' . ucfirst($this->controllerName);
        $controller = '';

        try {
            $this->model->validator->checkClassExist($controllerName);
            $controller = new $controllerName;
        } catch (NotExistClassException $e) {
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
     * Redirect 404 page
     */
    public static function errorPage404(): void
    {
        $host = 'http://' . $_SERVER['HTTP_HOST'] . '/404';
        header("HTTP/1.1 404 Not Found");
        header('Location:' . $host);
    }
}