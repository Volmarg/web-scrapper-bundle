<?php

namespace WebScrapperBundle\Controller\Debug;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use WebScrapperBundle\Service\CrawlerService;
use WebScrapperBundle\Service\HeaderExtractor\CookieExtractorService;

/**
 * Debug endpoints for testing the cookies related logic
 */
class CookieHandlingAction extends AbstractController
{
    public function __construct(
        private readonly CrawlerService $crawlerService,
        private readonly CookieExtractorService $cookieExtractorService
    ){}

    /**
     * @return JsonResponse
     */
    #[Route("/debug/cookie/test", name: "debug.cookie.test", methods: Request::METHOD_GET)]
    public function test(): JsonResponse
    {
        $cookieFetchUrl = "https://pl.jooble.org/";
        $cookies        = $this->cookieExtractorService->extractFromUrlResponse($cookieFetchUrl);

        return new JsonResponse([$cookies]);
    }
}