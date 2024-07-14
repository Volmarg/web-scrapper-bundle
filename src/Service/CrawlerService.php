<?php

namespace WebScrapperBundle\Service;

use Exception;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;
use WebScrapperBundle\Bundle\ProxyProviderService;
use WebScrapperBundle\Constants\UserAgentConstants;
use WebScrapperBundle\DTO\CrawlerConfigurationDto;
use WebScrapperBundle\Service\CrawlerEngine\BrowserShotCrawlerEngineService;
use WebScrapperBundle\Service\CrawlerEngine\CliCurlCrawlerEngineService;
use WebScrapperBundle\Service\CrawlerEngine\CrawlerEngineServiceInterface;
use WebScrapperBundle\Service\CrawlerEngine\Goutte\GoutteCrawlerEngineService;
use WebScrapperBundle\Service\CrawlerEngine\PantherCrawlerEngineService;
use WebScrapperBundle\Service\CrawlerEngine\RawCurlCrawlerEngineService;
use WebScrapperBundle\Service\CrawlerEngine\SpatieCrawlerEngineService;
use WebScrapperBundle\Service\ScrapEngine\HeadlessBrowserInterface;
use WebScrapperBundle\Service\ScrapEngine\ScrapEngineInterface;

/**
 * Handles crawling by using one of the available engines
 * Info:
 *  - had working solution with Puppeter once but it was doing tones of calls and was not so effective compared to normal headless
 */
class CrawlerService
{
    public const DEFAULT_USER_AGENT = UserAgentConstants::INSOMNIA;

    const MICROSECONDS_IN_MILLISECOND = 1000;

    const CRAWLER_ENGINE_GOUTTE      = 'goutte'; // BrowserKit (based on goutte / replaces it now)
    const CRAWLER_ENGINE_PANTHER     = "panther";
    const CRAWLER_ENGINE_SPATIE      = "spatie";
    const CRAWLER_ENGINE_BROWSERSHOT = "browsershot";
    const CRAWLER_ENGINE_RAW_CURL    = "rawCurl";
    const CRAWLER_ENGINE_CLI_CURL    = "cliCurl";
    const SCRAP_ENGINE_HEADLESS      = "headless";

    const CRAWLER_ENGINE_TO_SERVICE_FQNS = [
        self::CRAWLER_ENGINE_GOUTTE      => GoutteCrawlerEngineService::class,
        self::CRAWLER_ENGINE_PANTHER     => PantherCrawlerEngineService::class,
        self::CRAWLER_ENGINE_SPATIE      => SpatieCrawlerEngineService::class,
        self::CRAWLER_ENGINE_BROWSERSHOT => BrowserShotCrawlerEngineService::class,
        self::CRAWLER_ENGINE_RAW_CURL    => RawCurlCrawlerEngineService::class,
        self::CRAWLER_ENGINE_CLI_CURL    => CliCurlCrawlerEngineService::class,
    ];

    public function __construct(
        private readonly HeadlessBrowserInterface $headlessBrowser,
        private readonly KernelInterface          $kernel,
        private readonly ProxyProviderService     $providerService
    ){}

    /**
     * Will pick the crawler engine and will crawl the uri
     *
     * @param CrawlerConfigurationDto $crawlerConfigurationDto
     * @return Crawler
     * @throws Exception
     */
    public function crawl(CrawlerConfigurationDto $crawlerConfigurationDto): Crawler
    {
        if( !is_null($crawlerConfigurationDto->getCrawlDelay()) ){
            $crawlDelayInMicroseconds = self::MICROSECONDS_IN_MILLISECOND * $crawlerConfigurationDto->getCrawlDelay();
            usleep($crawlDelayInMicroseconds);
        }

        return match ($crawlerConfigurationDto->getEngine()) {
            self::SCRAP_ENGINE_HEADLESS => $this->useHeadlessBrowser($crawlerConfigurationDto),
            default                     => $this->useCrawlerEngine($crawlerConfigurationDto),
        };
    }

    /**
     * @param CrawlerConfigurationDto $crawlerConfigurationDto
     *
     * @return Crawler
     * @throws Exception
     */
    private function useCrawlerEngine(CrawlerConfigurationDto $crawlerConfigurationDto): Crawler
    {
        $crawlerEngineServiceFqns = self::CRAWLER_ENGINE_TO_SERVICE_FQNS[$crawlerConfigurationDto->getEngine()];
        if( empty($crawlerEngineServiceFqns) ){
            $allowedEnginesJson = json_encode(array_keys(self::CRAWLER_ENGINE_TO_SERVICE_FQNS), JSON_PRETTY_PRINT);
            throw new Exception("Unsupported crawler engine: {$crawlerConfigurationDto->getEngine()}!. Allowed are: {$allowedEnginesJson}.");
        }

        /** @var CrawlerEngineServiceInterface $crawlerEngine */
        $crawlerEngine = new $crawlerEngineServiceFqns($this->kernel, $this->providerService);
        $crawler       = $crawlerEngine->crawl($crawlerConfigurationDto);

        return $crawler;
    }

    /**
     * Even tho its headless browser, it can still be considered crawler engine
     *
     * @param CrawlerConfigurationDto $crawlerConfigurationDto
     *
     * @return Crawler
     */
    private function useHeadlessBrowser(CrawlerConfigurationDto $crawlerConfigurationDto): Crawler
    {
        $this->setDefaultHeadlessBrowserExtraConfig($crawlerConfigurationDto);

        $config = [
            ...$crawlerConfigurationDto->getExtraConfig(),
            ...$crawlerConfigurationDto->getProxyConfigAsArray()
        ];

        $pageContent = $this->headlessBrowser->scrap(
            $crawlerConfigurationDto->getUri(),
            $config,
        );
        $crawler     = new Crawler($pageContent, $crawlerConfigurationDto->getUri());

        return $crawler;
    }

    /**
     * Will some default extra config needed to make the headless browser work properly,
     * some options are added "as really needed", other are added due to legacy support
     * - meaning that previously some options were passed / set differently and this still has to work
     *
     * Not returning anything because the object is being manipulated directly so there is no
     * need to return its changed state.
     *
     * @param CrawlerConfigurationDto $crawlerConfigurationDto
     *
     * @return void
     */
    private function setDefaultHeadlessBrowserExtraConfig(CrawlerConfigurationDto $crawlerConfigurationDto): void
    {
        if (!$crawlerConfigurationDto->hasSingleExtraConfig(ScrapEngineInterface::CONFIGURATION_USER_AGENT)) {
            $crawlerConfigurationDto->addSingleExtraConfig(ScrapEngineInterface::CONFIGURATION_USER_AGENT, self::DEFAULT_USER_AGENT);
        }
    }

}