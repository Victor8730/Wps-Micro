<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Validator;
use Core\ViewRenderer;
use Exceptions\ValidationException;
use Services\AuthService;

class ControllerAuth extends Controller
{
    /**
     * Session storage used for authentication messages.
     */
    private Session $session;

    /**
     * Authentication service.
     */
    private AuthService $auth;

    /**
     * Prepare the authentication controller dependencies.
     */
    public function __construct(
        Request $request,
        ViewRenderer $view,
        Validator $validator,
        Session $session,
        AuthService $auth
    ) {
        $this->session = $session;
        $this->auth = $auth;

        parent::__construct($request, $view, $validator);
    }

    /**
     * Render the login form.
     */
    public function actionLogin(): Response
    {
        if ($this->auth->check()) {
            return $this->redirect('/account');
        }

        return $this->render('auth/login.twig', ['auth_user' => null]);
    }

    /**
     * Authenticate the submitted credentials.
     *
     * @throws ValidationException
     */
    public function actionAuthenticate(): Response
    {
        $credentials = $this->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|max:255',
        ]);

        if (!$this->auth->attempt($credentials['email'], $credentials['password'])) {
            throw new ValidationException([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = $this->auth->user();
        $this->session->flash('success', 'Welcome back, ' . ($user['name'] ?? 'user') . '.');

        return $this->redirect('/account');
    }

    /**
     * Render the registration form.
     */
    public function actionRegister(): Response
    {
        if ($this->auth->check()) {
            return $this->redirect('/account');
        }

        return $this->render('auth/register.twig', ['auth_user' => null]);
    }

    /**
     * Create and authenticate a new user.
     *
     * @throws ValidationException
     */
    public function actionStore(): Response
    {
        $data = $this->validate([
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|max:255|confirmed',
        ]);

        $user = $this->auth->register($data['name'], $data['email'], $data['password']);

        if ($user === null) {
            throw new ValidationException([
                'email' => ['A user with this email address already exists.'],
            ]);
        }

        $this->session->flash('success', 'Your account has been created.');

        return $this->redirect('/account');
    }

    /**
     * Render the authenticated account page.
     */
    public function actionAccount(): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        return $this->render('auth/account.twig', ['auth_user' => $user]);
    }

    /**
     * End the current authenticated session.
     */
    public function actionLogout(): Response
    {
        $this->auth->logout();
        $this->session->flash('success', 'You have been logged out.');

        return $this->redirect('/login');
    }
}
