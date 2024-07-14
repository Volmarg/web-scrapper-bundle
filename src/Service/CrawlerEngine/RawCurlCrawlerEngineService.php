<?php

namespace WebScrapperBundle\Service\CrawlerEngine;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;
use WebScrapperBundle\Bundle\ProxyProviderService;
use WebScrapperBundle\DTO\CrawlerConfigurationDto;
use WebScrapperBundle\Service\Analyse\ProtectedWebsiteAnalyser;
use WebScrapperBundle\Service\CrawlerEngine\Goutte\GoutteCrawlerEngineService;
use WebScrapperBundle\Service\Proxy\CallWithUnlockerHandler;
use WebScrapperBundle\Service\ScrapEngine\RawCurlScrapEngine;
use WebScrapperBundle\Service\ScrapEngine\ScrapEngineInterface;

/**
 * Plain curl based data fetching - slower than for example {@see GoutteCrawlerEngineService}
 * does not support js based pages,
 */
class RawCurlCrawlerEngineService implements CrawlerEngineServiceInterface
{

    public function __construct(
        private readonly KernelInterface      $kernel,
        private readonly ProxyProviderService $proxyProviderService
    ) {

    }

    /**
     * Not using recall with unlocker on purpose, because this logic is already triggered inside {@see RawCurlScrapEngine::scrap()}
     *
     * {@inheritDoc}
     * @throws Exception|GuzzleException
     */
    public function crawl(CrawlerConfigurationDto $crawlerConfigurationDto): Crawler
    {
        if (ProtectedWebsiteAnalyser::isAntiCrawlingDomain($crawlerConfigurationDto->getUri())) {
            CallWithUnlockerHandler::setProxyForCrawlerConfigurationDto($crawlerConfigurationDto);
        }

        $rawCurlScrapEngine = new RawCurlScrapEngine($this->kernel, $this->proxyProviderService);
        $pageContent = $rawCurlScrapEngine->scrap(
            $crawlerConfigurationDto->getUri(),
            [
                ScrapEngineInterface::CONFIGURATION_USER_AGENT => $crawlerConfigurationDto->getUserAgent(),
                ScrapEngineInterface::CONFIGURATION_HEADERS    => $crawlerConfigurationDto->getHeaders(),
                ...$crawlerConfigurationDto->getProxyConfigAsArray(),
            ],
        );
        $crawler     = new Crawler($pageContent, $crawlerConfigurationDto->getUri());

        return $crawler;
    }

}