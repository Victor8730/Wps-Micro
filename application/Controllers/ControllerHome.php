<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Response;

class ControllerHome extends Controller
{
    /**
     * Render the home page.
     */
    public function actionIndex(): Response
    {
        return $this->render('home/' . $this->getNameView());
    }
}
