<?php

namespace WebScrapperBundle\Service\ScrapEngine\Traits;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use WebScrapperBundle\DTO\ProxyConnectionConfigDto;
use WebScrapperBundle\Service\Proxy\CallWithUnlockerHandler;

trait ReCallWithUnlockerAwareTrait
{
    /**
     * Will do one more call for crawling if the content is locked behind some anti-crawling mechanism.
     * Next call happens with special unlocking proxy/mechanism
     *
     * @param string                   $url
     * @param array                    $configurationData
     * @param ProxyConnectionConfigDto $proxyConnectionConfigDto
     * @param string                   $originalResult
     *
     * @return string
     *
     * @throws GuzzleException
     * @throws Exception
     */
    private function reCallForUnlockerIfNeeded(
        string $url,
        array $configurationData,
        ProxyConnectionConfigDto $proxyConnectionConfigDto,
        string $originalResult
    ): string
    {
        if (!property_exists($this, 'isAntiCrawlingUnlockerUsed')) {
            throw new Exception("This class does not have property named: isAntiCrawlingUnlockerUsed");
        }

        if (!method_exists($this, 'scrap')) {
            throw new Exception("This class does not have method named: scrap");
        }

        if (
            $proxyConnectionConfigDto->canReCallWithAntiCrawlingUnlock()
            && !$this->isAntiCrawlingUnlockerUsed
        ) {
            $this->isAntiCrawlingUnlockerUsed = true;
            $configurationData = CallWithUnlockerHandler::setProxyConnectionForConfigurationArray($configurationData);
            return $this->scrap($url, $configurationData);
        }

        return $originalResult;
    }

}