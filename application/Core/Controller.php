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
     * CSRF helper.
     */
    protected Csrf $csrf;

    /**
     * Request validator.
     */
    protected Validator $validator;

    /**
     * Prepare the view renderer and request helpers.
     */
    public function __construct(
        Request $request,
        \Twig\Environment $view,
        Session $session,
        Csrf $csrf,
        Validator $validator
    ) {
        $this->request = $request;
        $this->view = $view;
        $this->session = $session;
        $this->csrf = $csrf;
        $this->validator = $validator;
        $this->isAjax = $this->request->isAjax();
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

    /**
     * Store current request input for the next response.
     */
    protected function flashInput(): void
    {
        $this->session->flash('old_input', $this->request->getRequest());
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
