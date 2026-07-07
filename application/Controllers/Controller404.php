<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;

class Controller404 extends Controller
{
    /**
     * Render the 404 page.
     */
    public function actionIndex(): void
    {
        echo $this->view->render($this->getNameView());
    }
}
