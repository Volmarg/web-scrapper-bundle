<?php

namespace WebScrapperBundle\Service\Proxy;

use ProxyProviderBridge\Enum\ProxyUsageEnum;
use WebScrapperBundle\DTO\CrawlerConfigurationDto;
use WebScrapperBundle\Service\Env\EnvReader;
use WebScrapperBundle\Service\ScrapEngine\ScrapEngineInterface;

/**
 * Handles manipulating the used configurations in a way where new proxy configuration is getting set
 * so the proxy based call will be handled via unlocker-proxy
 *
 */
class CallWithUnlockerHandler
{
    /**
     * @param CrawlerConfigurationDto $dto
     *
     * @return CrawlerConfigurationDto
     */
    public static function setProxyForCrawlerConfigurationDto(CrawlerConfigurationDto $dto): CrawlerConfigurationDto
    {
        if (!self::canUseUnlocker()) {
            return $dto;
        }

        $dto->setProxyUsage(ProxyUsageEnum::UNLOCKER->value);
        $dto->setProxyProvider(null);
        $dto->setProxyCountryIsoCode(null);
        $dto->setUsedProxyIdentifier(null);

        return $dto;
    }

    /**
     * @param array $configuration
     *
     * @return array
     */
    public static function setProxyConnectionForConfigurationArray(array $configuration): array
    {
        if (!self::canUseUnlocker()) {
            return $configuration;
        }

        $configuration[ScrapEngineInterface::CONFIGURATION_PROXY_USAGE]            = ProxyUsageEnum::UNLOCKER->value;
        $configuration[ScrapEngineInterface::CONFIGURATION_PROXY_PROVIDER]         = null;
        $configuration[ScrapEngineInterface::CONFIGURATION_PROXY_COUNTRY_ISO_CODE] = null;
        $configuration[ScrapEngineInterface::CONFIGURATION_USED_PROXY_IDENTIFIER]  = null;

        return $configuration;
    }

    /**
     * Using the unlocker on non-prod env is not allowed as the costs of it are too high for playing around,
     * for testing the unlocker, refer to the notes left in the ProxyManager project as there is a way to play around
     * with "test" unlocker, but it handles only certain websites.
     *
     * @return bool
     */
    private static function canUseUnlocker(): bool
    {
        return !EnvReader::isDev();
    }

}
