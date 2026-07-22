<?php

declare(strict_types=1);

namespace WpsMicro\Tests\Fixtures;

use WpsMicro\Core\Controller;
use WpsMicro\Core\JsonResponse;
use WpsMicro\Core\Response;

final class KernelController extends Controller
{
    public function show(string $name): Response
    {
        return $this->render('kernel.twig', [
            'name' => $name,
            'method' => $this->request->getMethod(),
        ]);
    }

    public function status(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }
}
