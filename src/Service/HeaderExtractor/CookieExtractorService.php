<?php

namespace WebScrapperBundle\Service\HeaderExtractor;

use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpKernel\KernelInterface;
use WebScrapperBundle\Bundle\ProxyProviderService;
use WebScrapperBundle\Service\ScrapEngine\RawCurlScrapEngine;

/**
 * Handles extracting the cookies from request as sometimes it's impossible to call the site without having cookies
 * in request.
 *
 * Take for example this page:
 * - {@link https://pl.jooble.org/desc/-3179389840434285924}
 *
 * it won't work when called with crawler because it wants to have cookies first, and the cookies have to be obtained
 * for example from:
 * - {@link https://pl.jooble.org/}
 *
 * This will however NOT set all the cookies that are there, because some cookies might be set via js. and this code
 * won't be able to handle that
 */
class CookieExtractorService
{
    private const SET_COOKIES_KEY = "Set-Cookie";

    /**
     * @var RawCurlScrapEngine $curlScrapEngine
     */
    private readonly RawCurlScrapEngine $curlScrapEngine;

    public function __construct(
        readonly KernelInterface      $kernel,
        readonly ProxyProviderService $proxyProviderService
    ){
        $this->curlScrapEngine = new RawCurlScrapEngine($kernel, $proxyProviderService);
    }

    /**
     * Will call given url and extract cookies from the response.
     * Afterwards return the cookies as cookie string
     *
     * @param string $targetUrl
     *
     * @return string
     *
     * @throws GuzzleException
     */
    public function extractFromUrlResponse(string $targetUrl): string
    {
        $curlDto       = $this->curlScrapEngine->scrapWithHeaders($targetUrl);
        $headers       = $curlDto->getHeaders();
        $cookies       = $this->extractFromArray($headers);
        $cookiesString = implode(";", $cookies);

        return $cookiesString;
    }

    /**
     * Will return array of cookies
     *
     * @param array $headers
     *
     * @return array
     */
    private function extractFromArray(array $headers): array
    {
        return $headers[self::SET_COOKIES_KEY] ?? $headers[strtolower(self::SET_COOKIES_KEY)];
    }

}