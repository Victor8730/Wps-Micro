<?php

declare(strict_types=1);

namespace Core;

class Controller extends Base
{
    /**
     * Twig view renderer.
     */
    protected \Twig\Environment $view;

    /**
     * Indicates whether the current request is an XMLHttpRequest request.
     */
    protected bool $isAjax;

    /**
     * Prepare the view renderer and request helpers.
     */
    public function __construct()
    {
        $cachePath = Base::PATH_ROOT . '/' . Base::PATH_APPLICATION . '/' . Base::PATH_CACHE;

        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0775, true);
        }

        $loader = new \Twig\Loader\FilesystemLoader(
            Base::PATH_ROOT . '/' . Base::PATH_APPLICATION . '/' . Base::PATH_VIEWS
        );
        $this->view = new \Twig\Environment($loader, [
            'cache' => $cachePath,
            'auto_reload' => true,
            'autoescape' => 'html',
        ]);
        $this->isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

        parent::__construct();
    }

    /**
     * Return the current controller class name.
     */
    public function __toString(): string
    {
        return get_class($this);
    }

    /**
     * Send a JSON response for an AJAX request.
     */
    protected function ajaxResponse(bool $success = true, string $message = ''): void
    {
        $response = [
            'success' => $success,
            'message' => $message,
        ];

        header('Content-Type: application/json; charset=utf-8');

        exit(json_encode($response, JSON_THROW_ON_ERROR));
    }

    /**
     * Build a default Twig template name from the current controller name.
     */
    protected function getNameView(): string
    {
        $nameTemplate = explode('\Controller', $this->__toString());

        return mb_strtolower($nameTemplate[1]) . '.twig';
    }
}
