<?php

namespace WebScrapperBundle\DTO;

use WebScrapperBundle\Constants\UserAgentConstants;
use WebScrapperBundle\Service\Analyse\ProtectedWebsiteAnalyser;
use WebScrapperBundle\Service\CrawlerEngine\Goutte\GoutteCrawlerInterface;
use WebScrapperBundle\Service\ScrapEngine\ScrapEngineInterface;

/**
 * Configuration of crawler - will determine :
 * - which engine will be used,
 * - which uri will be scrapped,
 * - how long should the crawler wait before fetching data,
 * - etc.
 */
class CrawlerConfigurationDto
{

    private const DEFAULT_MAX_REDIRECTS   = 10;
    private const DEFAULT_COOKIE_LIFETIME = 600; // seconds -> 10 min

    /**
     * Example:
     * - {@see GoutteCrawlerInterface::OPTION_MAX_DURATION}
     */
    private const CONNECTION_AX_LIFETIME = 10; // seconds

    /**
     * @var string $uri
     */
    private string $uri;

    /**
     * @var string $engine
     */
    private string $engine;

    /**
     * @var string $waitForDomSelectorName
     */
    private string $waitForDomSelectorName = "";

    /**
     * @var string $jsFunctionToBeCalledAndWaitedForTrueToReturn
     */
    private string $jsFunctionToBeCalledAndWaitedForTrueToReturn = "";

    /**
     * @var int|null $waitMillisecondBeforeJsContentFetch
     */
    private ?int $waitMillisecondBeforeJsContentFetch = null;

    /**
     * @var int|null $crawlDelay
     */
    private ?int $crawlDelay = null;

    /**
     * @var array $headers
     */
    private array $headers = [];

    /**
     * @var int $maxRedirects
     */
    private int $maxRedirects = CrawlerConfigurationDto::DEFAULT_MAX_REDIRECTS;

    /**
     * @var int $connectionMaxLifetime
     */
    private int $connectionMaxLifetime = self::CONNECTION_AX_LIFETIME;

    /**
     * @var string|null $cookieProvidingUrl
     */
    private ?string $cookieProvidingUrl = null;

    /**
     * @var int $cookieMaxLifetime
     */
    private int $cookieMaxLifetime = self::DEFAULT_COOKIE_LIFETIME;

    /**
     * Any extra configuration that cannot be unified due to for example some options being usable only by one
     * specific engine
     *
     * @var array $extraConfig
     */
    private array $extraConfig = [];

    /**
     * Even tho it's sometimes being added to headers, in some cases it must be explicitly told
     * which user agent will be used as some crawlers require the user agent to be set in few places,
     * - once in headers,
     * - other time in some extra "setters" etc.
     *
     * @var string $userAgent
     */
    private string $userAgent = UserAgentConstants::CHROME_85;

    /**
     * @var bool $withProxy
     */
    private bool $withProxy = false;

    /**
     * @var string|null $usedProxyIdentifier
     */
    private ?string $usedProxyIdentifier = null;

    /**
     * @var string|null $proxyUsage
     */
    private ?string $proxyUsage = null;

    /**
     * @var string|null $proxyCountryIsoCode
     */
    private ?string $proxyCountryIsoCode = null;

    /**
     * @var string|null $proxyProvider
     */
    private ?string $proxyProvider = null;

    /**
     * If this is set to true, then in case when original call will fail due to anti-crawling protection
     * (detected via: {@see ProtectedWebsiteAnalyser::isAntiCrawling()}), another call will be made with special
     * unlocker proxy.
     *
     * @var bool $reCallWithAntiCrawlingUnlock
     */
    private bool $reCallWithAntiCrawlingUnlock = false;

    public function __construct(
        string $uri,
        string $engine,
        string $waitForDomSelectorName                       = "",
        string $jsFunctionToBeCalledAndWaitedForTrueToReturn = "",
        ?int   $waitMillisecondBeforeJsContentFetch          = null,
        ?int   $crawlDelay                                   = null
    )
    {
        $this->uri                                          = $uri;
        $this->engine                                       = $engine;
        $this->crawlDelay                                   = $crawlDelay;
        $this->waitForDomSelectorName                       = $waitForDomSelectorName;
        $this->waitMillisecondBeforeJsContentFetch          = $waitMillisecondBeforeJsContentFetch;
        $this->jsFunctionToBeCalledAndWaitedForTrueToReturn = $jsFunctionToBeCalledAndWaitedForTrueToReturn;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @param string $uri
     */
    public function setUri(string $uri): void
    {
        $this->uri = $uri;
    }

    /**
     * @return string
     */
    public function getEngine(): string
    {
        return $this->engine;
    }

    /**
     * @param string $engine
     */
    public function setEngine(string $engine): void
    {
        $this->engine = $engine;
    }

    /**
     * @return string
     */
    public function getWaitForDomSelectorName(): string
    {
        return $this->waitForDomSelectorName;
    }

    /**
     * @param string $waitForDomSelectorName
     */
    public function setWaitForDomSelectorName(string $waitForDomSelectorName): void
    {
        $this->waitForDomSelectorName = $waitForDomSelectorName;
    }

    /**
     * @return string
     */
    public function getJsFunctionToBeCalledAndWaitedForTrueToReturn(): string
    {
        return $this->jsFunctionToBeCalledAndWaitedForTrueToReturn;
    }

    /**
     * @param string $jsFunctionToBeCalledAndWaitedForTrueToReturn
     */
    public function setJsFunctionToBeCalledAndWaitedForTrueToReturn(string $jsFunctionToBeCalledAndWaitedForTrueToReturn): void {
        $this->jsFunctionToBeCalledAndWaitedForTrueToReturn = $jsFunctionToBeCalledAndWaitedForTrueToReturn;
    }

    /**
     * @return int|null
     */
    public function getWaitMillisecondBeforeJsContentFetch(): ?int
    {
        return $this->waitMillisecondBeforeJsContentFetch;
    }

    /**
     * @param int|null $waitMillisecondBeforeJsContentFetch
     */
    public function setWaitMillisecondBeforeJsContentFetch(?int $waitMillisecondBeforeJsContentFetch): void
    {
        $this->waitMillisecondBeforeJsContentFetch = $waitMillisecondBeforeJsContentFetch;
    }

    /**
     * @return int|null
     */
    public function getCrawlDelay(): ?int
    {
        return $this->crawlDelay;
    }

    /**
     * @param int|null $crawlDelay
     */
    public function setCrawlDelay(?int $crawlDelay): void
    {
        $this->crawlDelay = $crawlDelay;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    /**
     * @return string
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * @param string $userAgent
     */
    public function setUserAgent(string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    /**
     * @return int
     */
    public function getMaxRedirects(): int
    {
        return $this->maxRedirects;
    }

    /**
     * @param int $maxRedirects
     */
    public function setMaxRedirects(int $maxRedirects): void
    {
        $this->maxRedirects = $maxRedirects;
    }

    /**
     * @return int
     */
    public function getConnectionMaxLifetime(): int
    {
        return $this->connectionMaxLifetime;
    }

    /**
     * @param int $connectionMaxLifetime
     */
    public function setConnectionMaxLifetime(int $connectionMaxLifetime): void
    {
        $this->connectionMaxLifetime = $connectionMaxLifetime;
    }

    /**
     * @return string|null
     */
    public function getCookieProvidingUrl(): ?string
    {
        return $this->cookieProvidingUrl;
    }

    /**
     * @param string|null $cookieProvidingUrl
     */
    public function setCookieProvidingUrl(?string $cookieProvidingUrl): void
    {
        $this->cookieProvidingUrl = $cookieProvidingUrl;
    }

    /**
     * @return int
     */
    public function getCookieMaxLifetime(): int
    {
        return $this->cookieMaxLifetime;
    }

    /**
     * @param int $cookieMaxLifetime
     */
    public function setCookieMaxLifetime(int $cookieMaxLifetime): void
    {
        $this->cookieMaxLifetime = $cookieMaxLifetime;
    }

    /**
     * @return array
     */
    public function getExtraConfig(): array
    {
        return $this->extraConfig;
    }

    /**
     * @param array $extraConfig
     */
    public function setExtraConfig(array $extraConfig): void
    {
        $this->extraConfig = $extraConfig;
    }

    /**
     * @param string                $key
     * @param string|int|float|null $value
     *
     * @return void
     */
    public function addSingleExtraConfig(string $key, string|int|float|null $value): void
    {
        $this->extraConfig[$key] = $value;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getSingleExtraConfig(string $key): mixed
    {
        return $this->extraConfig[$key];
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasSingleExtraConfig(string $key): bool
    {
        return isset($this->extraConfig[$key]);
    }

    /**
     * @return bool
     */
    public function isWithProxy(): bool
    {
        return $this->withProxy;
    }

    /**
     * @param bool $withProxy
     */
    public function setWithProxy(bool $withProxy): void
    {
        $this->withProxy = $withProxy;
    }

    /**
     * @return string|null
     */
    public function getUsedProxyIdentifier(): ?string
    {
        return $this->usedProxyIdentifier;
    }

    /**
     * @param string|null $usedProxyIdentifier
     */
    public function setUsedProxyIdentifier(?string $usedProxyIdentifier): void
    {
        $this->usedProxyIdentifier = $usedProxyIdentifier;
    }

    /**
     * @return string|null
     */
    public function getProxyUsage(): ?string
    {
        return $this->proxyUsage;
    }

    /**
     * @param string|null $proxyUsage
     */
    public function setProxyUsage(?string $proxyUsage): void
    {
        $this->proxyUsage = $proxyUsage;
    }

    /**
     * @return string|null
     */
    public function getProxyCountryIsoCode(): ?string
    {
        return $this->proxyCountryIsoCode;
    }

    /**
     * @param string|null $proxyCountryIsoCode
     */
    public function setProxyCountryIsoCode(?string $proxyCountryIsoCode): void
    {
        $this->proxyCountryIsoCode = $proxyCountryIsoCode;
    }

    /**
     * @return string|null
     */
    public function getProxyProvider(): ?string
    {
        return $this->proxyProvider;
    }

    /**
     * @param string|null $proxyProvider
     */
    public function setProxyProvider(?string $proxyProvider): void
    {
        $this->proxyProvider = $proxyProvider;
    }

    /**
     * @return bool
     */
    public function canRecallWithAntiCrawlingUnlock(): bool
    {
        return $this->reCallWithAntiCrawlingUnlock;
    }

    /**
     * @param bool $reCallWithAntiCrawlingUnlock
     */
    public function setReCallWithAntiCrawlingUnlock(bool $reCallWithAntiCrawlingUnlock): void
    {
        $this->reCallWithAntiCrawlingUnlock = $reCallWithAntiCrawlingUnlock;
    }

    /**
     * @return array
     */
    public function getProxyConfigAsArray(): array
    {
        return [
            ScrapEngineInterface::CONFIGURATION_USE_PROXY                             => $this->isWithProxy(),
            ScrapEngineInterface::CONFIGURATION_USED_PROXY_IDENTIFIER                 => $this->getUsedProxyIdentifier(),
            ScrapEngineInterface::CONFIGURATION_PROXY_USAGE                           => $this->getProxyUsage(),
            ScrapEngineInterface::CONFIGURATION_PROXY_COUNTRY_ISO_CODE                => $this->getProxyCountryIsoCode(),
            ScrapEngineInterface::CONFIGURATION_PROXY_PROVIDER                        => $this->getProxyProvider(),
            ScrapEngineInterface::CONFIGURATION_CAN_RE_CALL_WITH_ANTI_CRAWLING_UNLOCK => $this->canRecallWithAntiCrawlingUnlock(),
        ];
    }

}