<?php

namespace WebScrapperBundle\Service\CrawlerEngine;

use Exception;
use Symfony\Component\DomCrawler\Crawler;
use WebScrapperBundle\DTO\CrawlerConfigurationDto;

/**
 * Allows executing js but must be configured if it should be used,
 * There are some configuration issues but should be more efficient
 * if there will be need of crawling things faster / often etc
 *
 * @link https://github.com/spatie/crawler
 */
class SpatieCrawlerEngineService implements CrawlerEngineServiceInterface
{

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function crawl(CrawlerConfigurationDto $crawlerConfigurationDto): Crawler
    {
        throw new \LogicException("This crawler is disabled. It's not used anywhere anyway. If You need it then You have to adjust it");

        /**
         * Uncomment if necessary to support later more than one url etc
         * The problem is that it will still require the Observer which intercepts response
         */
        //        $crawler = SpatieCrawler::create();
        //        $crawler
        //                ->setBrowsershot($browserShot)
        //                ->setCrawlObserver(new SpatieCrawlObserver())
        //                ->ignoreRobots()
        //                ->acceptNofollowLinks()
        //                ->executeJavaScript()
        //                ->setMaximumDepth(1)
        //                ->setConcurrency(1)
        //                ->setTotalCrawlLimit(1)
        //                ->setCurrentCrawlLimit(1)
        //                ->setUserAgent(self::USER_AGENT_CHROME_85)
        //                ->startCrawling($uri);

        throw new Exception("This method has been disabled now, please use other one!");
    }
}