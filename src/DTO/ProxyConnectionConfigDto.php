<?php

namespace WebScrapperBundle\DTO;

use WebScrapperBundle\Service\ScrapEngine\ScrapEngineInterface;

class ProxyConnectionConfigDto
{
    private bool $isWithProxy = false;
    private ?string $usedProxyIdentifier = null;
    private ?string $proxyUsage = null;
    private ?string $proxyCountryIsoCode = null;
    private ?string $proxyProvider                = null;
    private bool    $reCallWithAntiCrawlingUnlock = false;

    /**
     * @return bool
     */
    public function isWithProxy(): bool
    {
        return $this->isWithProxy;
    }

    /**
     * @param bool $isWithProxy
     */
    public function setIsWithProxy(bool $isWithProxy): void
    {
        $this->isWithProxy = $isWithProxy;
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
    public function canReCallWithAntiCrawlingUnlock(): bool
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
     * @param array $data
     *
     * @return self
     */
    public static function tryFromArray(array $data): self
    {
        $isWithProxy                  = $data[ScrapEngineInterface::CONFIGURATION_USE_PROXY] ?? false;
        $usedProxyIdentifier          = $data[ScrapEngineInterface::CONFIGURATION_USED_PROXY_IDENTIFIER] ?? null;
        $proxyUsage                   = $data[ScrapEngineInterface::CONFIGURATION_PROXY_USAGE] ?? null;
        $proxyCountryIsoCode          = $data[ScrapEngineInterface::CONFIGURATION_PROXY_COUNTRY_ISO_CODE] ?? null;
        $proxyProvider                = $data[ScrapEngineInterface::CONFIGURATION_PROXY_PROVIDER] ?? null;
        $reCallWithAntiCrawlingUnlock = $data[ScrapEngineInterface::CONFIGURATION_CAN_RE_CALL_WITH_ANTI_CRAWLING_UNLOCK] ?? false;

        $dto = new self();
        $dto->setIsWithProxy($isWithProxy);
        $dto->setUsedProxyIdentifier($usedProxyIdentifier);
        $dto->setProxyUsage($proxyUsage);
        $dto->setProxyCountryIsoCode($proxyCountryIsoCode);
        $dto->setProxyProvider($proxyProvider);
        $dto->setReCallWithAntiCrawlingUnlock($reCallWithAntiCrawlingUnlock);

        return $dto;
    }

    /**
     * @param CrawlerConfigurationDto $crawlerConfigurationDto
     *
     * @return self
     */
    public static function tryFromCrawlerConfiguration(CrawlerConfigurationDto $crawlerConfigurationDto): self
    {
        $dto = new self();
        $dto->setIsWithProxy($crawlerConfigurationDto->isWithProxy());
        $dto->setUsedProxyIdentifier($crawlerConfigurationDto->getUsedProxyIdentifier());
        $dto->setProxyUsage($crawlerConfigurationDto->getProxyUsage());
        $dto->setProxyCountryIsoCode($crawlerConfigurationDto->getProxyCountryIsoCode());
        $dto->setProxyProvider($crawlerConfigurationDto->getProxyProvider());
        $dto->setReCallWithAntiCrawlingUnlock($crawlerConfigurationDto->canRecallWithAntiCrawlingUnlock());

        return $dto;
    }

}
