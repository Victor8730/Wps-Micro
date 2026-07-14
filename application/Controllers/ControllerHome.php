<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Validator;
use Models\Home;

class ControllerHome extends Controller
{
    /**
     * Home page data model.
     */
    private Home $home;

     /**
     * Prepare the controller dependencies.
     */
    public function __construct(
        Request $request,
        \Twig\Environment $view,
        Session $session,
        Csrf $csrf,
        Validator $validator,
        Home $home
    ) {
        $this->home = $home;

        parent::__construct($request, $view, $session, $csrf, $validator);
    }

    /**
     * Render the home page.
     */
    public function actionIndex(): Response
    {
        return $this->render('home/' . $this->getNameView(), [
            'messages' => $this->home->latestMessages(),
        ]);
    }
}
