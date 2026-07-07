<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;

class ControllerHome extends Controller
{
    /**
     * Render the home page.
     */
    public function actionIndex(): void
    {
        echo $this->view->render('home/' . $this->getNameView());
    }
}
