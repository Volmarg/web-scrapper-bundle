<?php

namespace WebScrapperBundle\Service\ScrapEngine;

/**
 * Defines common logic for scrapping engine
 */
interface ScrapEngineInterface
{
    public const CONFIGURATION_USER_AGENT = "userAgent";
    public const CONFIGURATION_HEADERS    = "headers";
    public const CONFIGURATION_METHOD    = "method";
    public const CONFIGURATION_BODY    = "body";

    public const CONFIGURATION_USE_PROXY = "useProxy";

    public const CONFIGURATION_USED_PROXY_IDENTIFIER = "usedProxyIdentifier";
    public const CONFIGURATION_PROXY_USAGE = "proxyUsage";
    public const CONFIGURATION_PROXY_COUNTRY_ISO_CODE = "proxyCountryIsoCode";
    public const CONFIGURATION_PROXY_PROVIDER = "proxyProvider";
    public const CONFIGURATION_CAN_RE_CALL_WITH_ANTI_CRAWLING_UNLOCK = "reCallWithAntiCrawlingUnlock";

    /**
     * Will return content of scrapped page
     *
     * @param string $url
     * @param array  $configurationData
     *
     * @return string
     */
    public function scrap(string $url, array $configurationData = []): string;
}