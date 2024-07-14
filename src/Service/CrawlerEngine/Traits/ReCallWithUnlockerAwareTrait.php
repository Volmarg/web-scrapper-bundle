<?php

namespace WebScrapperBundle\Service\CrawlerEngine\Traits;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;
use WebScrapperBundle\DTO\CrawlerConfigurationDto;
use WebScrapperBundle\Exception\MissingDependencyException;
use WebScrapperBundle\Service\Proxy\CallWithUnlockerHandler;

trait ReCallWithUnlockerAwareTrait
{

    /**
     * Will do one more call for crawling if the content is locked behind some anti-crawling mechanism.
     * Next call happens with special unlocking proxy/mechanism
     *
     * @param CrawlerConfigurationDto $crawlerConfigurationDto
     * @param Crawler                 $originalCrawler
     *
     * @return Crawler
     *
     * @throws GuzzleException
     * @throws MissingDependencyException
     * @throws Exception
     */
    private function reCallForUnlockerIfNeeded(CrawlerConfigurationDto $crawlerConfigurationDto, Crawler $originalCrawler): Crawler
    {
        if (!property_exists($this, 'isAntiCrawlingUnlockerUsed')) {
            throw new Exception("This class does not have property named: isAntiCrawlingUnlockerUsed");
        }

        if (!method_exists($this, 'crawl')) {
            throw new Exception("This class does not have method named: crawl");
        }

        if (
            $crawlerConfigurationDto->canRecallWithAntiCrawlingUnlock()
            && !$this->isAntiCrawlingUnlockerUsed
        ) {
            $this->isAntiCrawlingUnlockerUsed = true;
            CallWithUnlockerHandler::setProxyForCrawlerConfigurationDto($crawlerConfigurationDto);
            return $this->crawl($crawlerConfigurationDto);
        }

        return $originalCrawler;
    }

}