<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


class ControllerHome extends Controller
{
    /**
     * Show page with template home
     */
    public function actionIndex()
    {
        try {
            echo $this->view->render('home/' . $this->getNameView());
        } catch (LoaderError $e) {
        } catch (RuntimeError $e) {
        } catch (SyntaxError $e) {
        }
    }
}