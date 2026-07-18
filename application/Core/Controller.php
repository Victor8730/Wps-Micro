<?php

declare(strict_types=1);

namespace Core;

class Controller
{
    /**
     * Current HTTP request.
     */
    protected Request $request;

    /**
     * Lazy view renderer.
     */
    protected ViewRenderer $view;

    /**
     * Request validator.
     */
    protected Validator $validator;

    /**
     * Prepare the view renderer and request helpers.
     */
    public function __construct(
        Request $request,
        ViewRenderer $view,
        Validator $validator
    ) {
        $this->request = $request;
        $this->view = $view;
        $this->validator = $validator;
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
     * Validate current request input.
     *
     * @throws \Exceptions\ValidationException
     */
    protected function validate(array $rules): array
    {
        return $this->validator->validate($this->request->all(), $rules);
    }

    /**
     * Build a redirect response.
     */
    protected function redirect(string $url): RedirectResponse
    {
        return new RedirectResponse($url);
    }
}
