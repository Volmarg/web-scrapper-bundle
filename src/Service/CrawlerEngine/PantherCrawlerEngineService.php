<?php

namespace WebScrapperBundle\Service\CrawlerEngine;


use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeoutException;
use GuzzleHttp\Exception\GuzzleException;
use LogicException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Panther\Client as PantherCrawler;
use WebScrapperBundle\Bundle\ProxyProviderService;
use WebScrapperBundle\DTO\CrawlerConfigurationDto;
use WebScrapperBundle\DTO\ProxyConnectionConfigDto;
use WebScrapperBundle\Service\Analyse\ProtectedWebsiteAnalyser;
use WebScrapperBundle\Service\Proxy\CallWithUnlockerHandler;

/**
 * Allows executing js - is a headless browser
 *
 * @link https://github.com/symfony/panther
 * @see https://askubuntu.com/questions/870530/how-to-install-geckodriver-in-ubuntu
 */
class PantherCrawlerEngineService implements CrawlerEngineServiceInterface
{

    public function __construct(
        private readonly KernelInterface      $kernel,
        private readonly ProxyProviderService $proxyProviderService
    ) {

    }

    /**
     * {@inheritDoc}
     *
     * This might come in handy when wanting to re-activate it:
     *
     * Panther requires chromium from snap but snap fails inside docker-container
     * - apt-get install chromium-chromedriver
     * - apt-get install chromium-browser
     *
     * Need to configure access via proxy:
     * - https://github.com/symfony/panther#using-a-proxy
     *
     * @throws NoSuchElementException
     * @throws TimeoutException
     * @throws GuzzleException
     */
    public function crawl(CrawlerConfigurationDto $crawlerConfigurationDto): Crawler
    {
        throw new LogicException(
            "This crawler is disabled. It worked, but now it causes issues inside docker container."
            . " It's not used anywhere anyway. If You need it then You have to adjust it"
        );

        if (ProtectedWebsiteAnalyser::isAntiCrawlingDomain($crawlerConfigurationDto->getUri())) {
            CallWithUnlockerHandler::setProxyForCrawlerConfigurationDto($crawlerConfigurationDto);
        }

        $proxyConfigDto = ProxyConnectionConfigDto::tryFromCrawlerConfiguration($crawlerConfigurationDto);
        if ($crawlerConfigurationDto->isWithProxy()) {
            $proxyConfigDto = $this->proxyProviderService->getConnectionData($proxyConfigDto);
        }

        $chromeBinary = exec("which chromium-browser");
        if (empty($chromeBinary)) {
            throw new LogicException("Could not get path to google-chrome binary, did You installed it?");
        }

        $client = PantherCrawler::createChromeClient(
            chromeDriverBinary: $chromeBinary,
        );

        $client->request(Request::METHOD_GET, $crawlerConfigurationDto->getUri());
        $crawler = $client->waitFor($crawlerConfigurationDto->getWaitForDomSelectorName());

        return $crawler;
    }
}