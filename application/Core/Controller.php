<?php

declare(strict_types=1);

namespace Core;

class Controller extends Base
{
    /**
     * Current HTTP request.
     */
    protected Request $request;

    /**
     * Twig view renderer.
     */
    protected \Twig\Environment $view;

    /**
     * Indicates whether the current request is an XMLHttpRequest request.
     */
    protected bool $isAjax;

    /**
     * Session storage helper.
     */
    protected Session $session;

    /**
     * Prepare the view renderer and request helpers.
     */
    public function __construct(Request $request, \Twig\Environment $view, Session $session)
    {
        $this->request = $request;
        $this->view = $view;
        $this->session = $session;
        $this->isAjax = $this->request->isAjax();

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
     * Build a JSON response for an AJAX request.
     */
    protected function ajaxResponse(bool $success = true, string $message = ''): JsonResponse
    {
        return $this->json([
            'success' => $success,
            'message' => $message,
        ]);
    }

    /**
     * Render a Twig template response.
     */
    protected function render(string $template, array $context = [], int $statusCode = 200): Response
    {
        return new Response($this->view->render($template, $context), $statusCode);
    }

    /**
     * Build a JSON response.
     */
    protected function json(array $data = [], int $statusCode = 200): JsonResponse
    {
        return new JsonResponse($data, $statusCode);
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
