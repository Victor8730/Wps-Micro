<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class Controller404 extends Controller
{
    /**
     * Show page with template 404
     */
    function actionIndex()
    {
        try {
            echo $this->view->render($this->getNameView());
        } catch (LoaderError $e) {
        } catch (RuntimeError $e) {
        } catch (SyntaxError $e) {
        }
    }
}