<?php

namespace WebScrapperBundle\Service\CrawlerEngine\Goutte;

use Exception;
use Symfony\Component\BrowserKit\HttpBrowser as GoutteCrawler;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use TypeError;
use WebScrapperBundle\Bundle\ProxyProviderService;
use WebScrapperBundle\Constants\UserAgentConstants;
use WebScrapperBundle\DTO\CrawlerConfigurationDto;
use WebScrapperBundle\DTO\ProxyConnectionConfigDto;
use WebScrapperBundle\Service\Analyse\ProtectedWebsiteAnalyser;
use WebScrapperBundle\Service\CrawlerEngine\CrawlerEngineServiceInterface;
use WebScrapperBundle\Service\CrawlerEngine\Traits\ReCallWithUnlockerAwareTrait;
use WebScrapperBundle\Service\HeaderExtractor\CookieExtractorService;
use WebScrapperBundle\Service\Proxy\CallWithUnlockerHandler;

/**
 * Standard crawler - won't handle js but is faster for standard scrapping
 *
 * Goutte became replaced by:
 * - {@link https://symfony.com/components/BrowserKit}
 *
 * The class name remains unchanged because:
 * - this way it's less work to replace all the calls to this service and the engine name,
 * - BrowserKit is actually based on Goutte,
 */
class GoutteCrawlerEngineService implements GoutteCrawlerInterface
{
    use ReCallWithUnlockerAwareTrait;

    private const DEFAULT_USER_AGENT = UserAgentConstants::CHROME_85;

    private bool $isAntiCrawlingUnlockerUsed = false;

    public function __construct(
        private readonly KernelInterface      $kernel,
        private readonly ProxyProviderService $proxyProviderService
    ) {

    }

    /**
     * {@inheritDoc}
     * @link https://github.com/FriendsOfPHP/Goutte/issues/401 - setting headers
     * @throws GuzzleException
     */
    public function crawl(CrawlerConfigurationDto $crawlerConfigurationDto): Crawler
    {
        $cookies = "";
        if (!empty($crawlerConfigurationDto->getCookieProvidingUrl())) {
            $cookieExtractor = new CookieExtractorService($this->kernel, $this->proxyProviderService);
            $cookies         = $cookieExtractor->extractFromUrlResponse($crawlerConfigurationDto->getCookieProvidingUrl());
        }

        if (ProtectedWebsiteAnalyser::isAntiCrawlingDomain($crawlerConfigurationDto->getUri())) {
            CallWithUnlockerHandler::setProxyForCrawlerConfigurationDto($crawlerConfigurationDto);
        }

        $headers   = $this->handleHeaders($crawlerConfigurationDto->getHeaders(), $cookies);
        $userAgent = $this->getUserAgent($headers);

        $requestParameters = $this->buildRequestParameters($headers);

        $callId         = null;
        $connectionDto  = null;
        $proxyConfigDto = ProxyConnectionConfigDto::tryFromCrawlerConfiguration($crawlerConfigurationDto);
        if ($crawlerConfigurationDto->isWithProxy()) {
            $connectionDto = $this->proxyProviderService->getConnectionData($proxyConfigDto);
        }

        if ($connectionDto?->doesExists()) {
            $callId = $this->proxyProviderService->storeCallData(
                $connectionDto->getIp(),
                $connectionDto->getPort(),
                $crawlerConfigurationDto->getUri()
            );

            $requestParameters[GoutteCrawlerInterface::OPTION_PROXY] = $connectionDto->getProxyString();
        }

        try {
            $httpClient = HttpClient::create($requestParameters);
            $client     = new GoutteCrawler($httpClient, null);

            $client->setMaxRedirects($crawlerConfigurationDto->getMaxRedirects());
            $client->setServerParameter('HTTP_USER_AGENT', $userAgent);

            /**
             * {@link https://php.watch/articles/curl-php-accept-encoding-compression}
             * In many cases this improves loading performance ~30-60%
             */
            $client->setServerParameter('HTTP_ACCEPT_ENCODING', "");

            $crawler = $client->request(Request::METHOD_GET, $crawlerConfigurationDto->getUri());

            if (ProtectedWebsiteAnalyser::isAntiCrawling($crawler, $client->getInternalResponse()->getHeaders(), $crawlerConfigurationDto->getUri())) {
                $crawler = $this->reCallForUnlockerIfNeeded($crawlerConfigurationDto, $crawler);
            }

        } catch (Exception|TypeError $e) {
            if ($crawlerConfigurationDto->isWithProxy() && !empty($callId)) {
                $this->proxyProviderService->updateCallData($callId, false);
            }

            throw $e;
        }

        if ($crawlerConfigurationDto->isWithProxy() && !empty($callId)) {
            $this->proxyProviderService->updateCallData($callId, true);
        }

        return $crawler;
    }

    /**
     * Will check if any data has to be set in headers, changed, normalized, removed etc.
     *
     * @param array $headers
     * @param string $cookies
     *
     * @return array
     */
    private function handleHeaders(array $headers, string $cookies): array
    {
        $modifiedHeaders = $this->handleUserAgentHeader($headers);
        $modifiedHeaders = $this->handleCookies($modifiedHeaders, $cookies);

        return $modifiedHeaders;
    }

    /**
     * Will check if user agent is set, if it is then nothing happens, else the {@see GoutteCrawlerEngineService::DEFAULT_USER_AGENT}
     * will be set
     *
     * @param $headers
     *
     * @return array
     */
    private function handleUserAgentHeader($headers): array
    {
        $arrayKeys = array_keys($headers);
        foreach ($arrayKeys as $arrayKey) {
            if (strtolower($arrayKey) === strtolower(CrawlerEngineServiceInterface::KEY_USER_AGENT)) {
                return $headers;
            }
        }

        $headers[CrawlerEngineServiceInterface::KEY_USER_AGENT] = self::DEFAULT_USER_AGENT;

        return $headers;
    }

    /**
     * Will return the user agent used for this connection
     *
     * @param array $headers
     *
     * @return string
     */
    private function getUserAgent(array $headers): string
    {
        $headerNames    = array_keys($headers);
        $userAgentIndex = null;
        foreach ($headerNames as $headerName) {
            if (strtolower($headerName) === strtolower(CrawlerEngineServiceInterface::KEY_USER_AGENT)) {
                $userAgentIndex = $headerName;
                break;
            }
        }

       return $headers[$userAgentIndex];
    }

    /**
     * Will set or extend the cookies
     *
     * Info: might cause issue when 2 the same cookies are being set where:
     * - one will be there already existing one in delivered headers,
     * - 2nd one will be from extraction made by for example: {@see CookieExtractorService}
     *
     * @param array $modifiedHeaders
     * @param string $cookies
     *
     * @return array
     */
    private function handleCookies(array $modifiedHeaders, string $cookies): array
    {
        $cookiesKey = CrawlerEngineServiceInterface::KEY_HEADER_COOKIE;
        if (!array_key_exists($cookiesKey, $modifiedHeaders)) {
            $cookiesKey = strtolower(CrawlerEngineServiceInterface::KEY_HEADER_COOKIE);
        }

        if (array_key_exists($cookiesKey, $modifiedHeaders)) {
            $existingCookies = $modifiedHeaders[$cookiesKey];
            if (!str_ends_with($existingCookies, ";")) {
                $existingCookies .= ";";
            }

            $allCookies                   = $existingCookies . $cookies;
            $modifiedHeaders[$cookiesKey] = $allCookies;
        }

        return $modifiedHeaders;
    }

    /**
     * @param array $headers
     *
     * @return array
     */
    private function buildRequestParameters(array $headers): array
    {
        return [
            CrawlerEngineServiceInterface::KEY_HEADERS     => $headers,
            GoutteCrawlerInterface::OPTION_SSL_VERIFY_PEER => false,
            GoutteCrawlerInterface::OPTION_SSL_VERIFY_HOST => false,
            GoutteCrawlerInterface::OPTION_CIPHERS         => "DEFAULT@SECLEVEL=1",
            GoutteCrawlerInterface::OPTION_MAX_DURATION    => $this->kernel->getContainer()->getParameter('scrap.config.max_timeout_seconds'),
        ];
    }

}
