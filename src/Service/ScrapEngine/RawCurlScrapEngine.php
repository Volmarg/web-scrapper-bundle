<?php

namespace WebScrapperBundle\Service\ScrapEngine;

use CurlHandle;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use ProxyProviderBridge\Dto\ConnectionDataDto;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;
use TypeError;
use WebScrapperBundle\Bundle\ProxyProviderService;
use WebScrapperBundle\Constants\UserAgentConstants;
use WebScrapperBundle\DTO\ProxyConnectionConfigDto;
use WebScrapperBundle\DTO\ScrapEngine\CurlResponse;
use WebScrapperBundle\Service\Analyse\ProtectedWebsiteAnalyser;
use WebScrapperBundle\Service\Proxy\CallWithUnlockerHandler;
use WebScrapperBundle\Service\ScrapEngine\Traits\ReCallWithUnlockerAwareTrait;

/**
 * Handles scrapping via raw CURL
 */
class RawCurlScrapEngine implements ScrapEngineInterface
{
    use ReCallWithUnlockerAwareTrait;

    private bool $isAntiCrawlingUnlockerUsed = false;

    public function __construct(
        private readonly KernelInterface      $kernel,
        private readonly ProxyProviderService $proxyProviderService
    ) {

    }

    /**
     * {@inheritDoc}
     * Is pre-configured so that it is quick,
     *
     * @return string
     *
     * @throws GuzzleException
     */
    public function scrap(string $url, array $configurationData = []): string
    {
        $proxyConfigDto     = ProxyConnectionConfigDto::tryFromArray($configurationData);
        $callId             = null;
        $proxyConnectionDto = null;
        if ($proxyConfigDto->isWithProxy()) {
            $proxyConnectionDto = $this->proxyProviderService->getConnectionData($proxyConfigDto);
        }

        if ($proxyConnectionDto?->doesExists()) {
            $callId = $this->proxyProviderService->storeCallData(
                $proxyConnectionDto->getIp(),
                $proxyConnectionDto->getPort(),
                $url
            );
        }

        $ch = $this->getConfiguredCurlHandle($url, $configurationData, $proxyConnectionDto);

        // Execute curl
        try {
            $pageContent = curl_exec($ch);
            curl_close($ch);
        } catch (Exception|TypeError $e) {
            if (!empty($callId)) {
                $this->proxyProviderService->updateCallData($callId, false);
            }
            throw $e;
        }

        if (!empty($callId)) {
            $this->proxyProviderService->updateCallData($callId, true);
        }

        if (ProtectedWebsiteAnalyser::isAntiCrawling(new Crawler($pageContent), [], $url)) {
            $pageContent = $this->reCallForUnlockerIfNeeded($url, $configurationData, $proxyConfigDto, $pageContent);
        }

        return $pageContent;
    }

    /**
     * {@see RawCurlScrapEngine::scrap()} - same thing but in here in addition it returns headers
     * which were returned in response.
     *
     * @param string $url
     * @param array  $configurationData
     *
     * @return CurlResponse
     * @throws GuzzleException
     */
    public function scrapWithHeaders(string $url, array $configurationData = []): CurlResponse
    {
        $proxyConfigDto     = ProxyConnectionConfigDto::tryFromArray($configurationData);
        $callId             = null;
        $proxyConnectionDto = null;
        if ($proxyConfigDto->isWithProxy()) {
            $proxyConnectionDto = $this->proxyProviderService->getConnectionData($proxyConfigDto);

            $callId = $this->proxyProviderService->storeCallData(
                $proxyConnectionDto->getIp(),
                $proxyConnectionDto->getPort(),
                $url
            );
        }

        try {
            $responseHeaders = [];
            $ch              = $this->getConfiguredCurlHandle($url, $configurationData, $proxyConnectionDto);

            /**
             * This function is called by curl for each header received
             *
             * Taken from:
             * - {@Link https://stackoverflow.com/questions/9183178/can-php-curl-retrieve-response-headers-and-body-in-a-single-request}
             */
            curl_setopt($ch, CURLOPT_HEADERFUNCTION,
                function ($curl, $header) use (&$responseHeaders) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) // ignore invalid headers
                        return $len;

                    $responseHeaders[strtolower(trim($header[0]))][] = trim($header[1]);

                    return $len;
                }
            );

            // Execute curl
            $pageContent = curl_exec($ch);
            curl_close($ch);
        } catch (Exception|TypeError $e) {
            if (!empty($callId)) {
                $this->proxyProviderService->updateCallData($callId, false);
            }

            throw $e;
        }

        if (!empty($callId)) {
            $this->proxyProviderService->updateCallData($callId, true);
        }

        $curlResponse = new CurlResponse($pageContent, $responseHeaders);

        if (ProtectedWebsiteAnalyser::isAntiCrawling(new Crawler($pageContent), $responseHeaders, $url)) {
            $curlResponse = $this->reCallWithHeadersForUnlockerIfNeeded($url, $configurationData, $proxyConfigDto, $curlResponse);
        }

        return $curlResponse;
    }

    /**
     * Returns configured curl ready for further processing
     *
     * @param string                 $url
     * @param array                  $configurationData
     * @param ConnectionDataDto|null $proxyConnectionData
     *
     * @return CurlHandle
     */
    private function getConfiguredCurlHandle(string $url, array $configurationData = [], ?ConnectionDataDto $proxyConnectionData = null): CurlHandle
    {
        $userAgent  = $configurationData[ScrapEngineInterface::CONFIGURATION_USER_AGENT] ?? UserAgentConstants::CHROME_43;
        $headers    = $configurationData[ScrapEngineInterface::CONFIGURATION_HEADERS]    ?? [];
        $maxTimeout = $this->kernel->getContainer()->getParameter('scrap.config.max_timeout_seconds');

        $ch = curl_init();

        // Optimization part
        curl_setopt($ch, CURLOPT_URL, $url); // url from which data will be fetched
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                 // return the content of given url
        curl_setopt($ch, CURLOPT_TCP_FASTOPEN, 1);                   // available since curl 7.49 (??) for faster connection
        curl_setopt($ch, CURLOPT_HEADER, 0);                         // do not fetch headers
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);      // skip trying to fetch with ipv6, use ipv4 instantly instead
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");                  // skip encoding, fetch data faster (only gzip as some servers mess up when more options are sent)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);                 // skip ssl validation - make call faster
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);                 // skip ssl validation - make call faster
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $maxTimeout);       // The number of seconds to wait while trying to connect
        curl_setopt($ch, CURLOPT_TIMEOUT, $maxTimeout);              // The maximum number of seconds to allow cURL functions to execute.
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);                    // extra headers

        // Additional necessary settings
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // follow the redirects if there is any

        // Pretending / tricking the host part
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent); // make host think that this is call from browser

        if ($proxyConnectionData?->doesExists()) {
            curl_setopt($ch, CURLOPT_PROXY, $proxyConnectionData->getProxyString());
        }

        return $ch;
    }

    /**
     * Will do one more call for crawling if the content is locked behind some anti-crawling mechanism.
     * Next call happens with special unlocking proxy/mechanism
     *
     * @param string                   $url
     * @param array                    $configurationData
     * @param ProxyConnectionConfigDto $proxyConnectionConfigDto
     * @param CurlResponse             $originalResult
     *
     * @return CurlResponse
     *
     * @throws GuzzleException
     */
    private function reCallWithHeadersForUnlockerIfNeeded(
        string $url,
        array $configurationData,
        ProxyConnectionConfigDto $proxyConnectionConfigDto,
        CurlResponse $originalResult
    ): CurlResponse
    {
        if (
            $proxyConnectionConfigDto->canReCallWithAntiCrawlingUnlock()
            && !$this->isAntiCrawlingUnlockerUsed
        ) {
            $configurationData = CallWithUnlockerHandler::setProxyConnectionForConfigurationArray($configurationData);
            $this->isAntiCrawlingUnlockerUsed = true;
            return $this->scrapWithHeaders($url, $configurationData);
        }

        return $originalResult;
    }

}