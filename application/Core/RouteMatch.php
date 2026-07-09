<?php

declare(strict_types=1);

namespace Core;

class RouteMatch
{
    /**
     * Controller class name.
     */
    private string $controllerClass;

    /**
     * Controller action method.
     */
    private string $actionMethod;

    /**
     * Create a route match value object.
     */
    public function __construct(string $controllerClass, string $actionMethod)
    {
        $this->controllerClass = $controllerClass;
        $this->actionMethod = $actionMethod;
    }

    /**
     * Return the matched controller class.
     */
    public function getControllerClass(): string
    {
        return $this->controllerClass;
    }

    /**
     * Return the matched action method.
     */
    public function getActionMethod(): string
    {
        return $this->actionMethod;
    }
}
