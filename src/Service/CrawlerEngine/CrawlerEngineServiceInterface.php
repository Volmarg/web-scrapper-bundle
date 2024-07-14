<?php

namespace WebScrapperBundle\Service\CrawlerEngine;

use Symfony\Component\DomCrawler\Crawler;
use WebScrapperBundle\DTO\CrawlerConfigurationDto;

/**
 * Common logic for crawler engine or common data
 */
interface CrawlerEngineServiceInterface
{
    const KEY_HEADERS = "headers";

    const KEY_USER_AGENT = "user-agent";

    const KEY_HEADER_COOKIE = "Cookie";

    /**
     * Will crawl provided uri for given conditions configuration
     *
     * @param CrawlerConfigurationDto $crawlerConfigurationDto
     * @return Crawler
     */
    public function crawl(CrawlerConfigurationDto $crawlerConfigurationDto): Crawler;
}