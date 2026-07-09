<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
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
    public function __construct(Request $request, \Twig\Environment $view, Home $home)
    {
        $this->home = $home;

        parent::__construct($request, $view);
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
