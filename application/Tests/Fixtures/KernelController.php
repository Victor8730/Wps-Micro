<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Core\Controller;
use Core\Response;

final class KernelController extends Controller
{
    public function show(string $name): Response
    {
        return $this->render('kernel.twig', [
            'name' => $name,
            'method' => $this->request->getMethod(),
        ]);
    }
}
