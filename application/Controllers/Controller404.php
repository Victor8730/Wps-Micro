<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Response;

class Controller404 extends Controller
{
    /**
     * Render the 404 page.
     */
    public function actionIndex(): Response
    {
        return $this->render($this->getNameView(), [], 404);
    }
}
