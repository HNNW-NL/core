<?php

namespace App\Module\Main\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Twig\Environment;

final class ErrorController extends AbstractController
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function show(\Throwable $exception): Response
    {
        $statusCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        $template = sprintf('errors/%d.html.twig', $statusCode);

        if (!$this->twig->getLoader()->exists($template)) {
            $template = 'errors/500.html.twig';
        }

        if (!$this->twig->getLoader()->exists($template)) {
            return new Response('Er is iets misgegaan.', $statusCode);
        }

        return $this->render($template, [], new Response('', $statusCode));
    }
}