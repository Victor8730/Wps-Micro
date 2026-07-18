<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Validator;
use Core\ViewRenderer;
use Models\Home;
use Services\AuthService;

class ControllerHome extends Controller
{
    /**
     * Home page data model.
     */
    private Home $home;

    /**
     * Authentication service used by the home page.
     */
    private AuthService $auth;

    /**
     * Prepare the controller dependencies.
     */
    public function __construct(
        Request $request,
        ViewRenderer $view,
        Validator $validator,
        Home $home,
        AuthService $auth
    ) {
        $this->home = $home;
        $this->auth = $auth;

        parent::__construct($request, $view, $validator);
    }

    /**
     * Render the home page.
     */
    public function actionIndex(): Response
    {
        return $this->render('home/home.twig', [
            'auth_user' => $this->auth->user(),
            'messages' => $this->home->latestMessages(),
        ]);
    }
}
