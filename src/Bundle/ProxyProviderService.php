<?php

namespace WebScrapperBundle\Bundle;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use ProxyProviderBridge\Dto\ConnectionDataDto;
use ProxyProviderBridge\Request\GetConnectionDataRequest;
use ProxyProviderBridge\Request\StoreCallDataRequest;
use ProxyProviderBridge\Request\UpdateCallDataRequest;
use ProxyProviderBridge\Service\BridgeService;
use WebScrapperBundle\DTO\ProxyConnectionConfigDto;
use WebScrapperBundle\Service\Env\EnvReader;

class ProxyProviderService
{
    public function __construct(
        private readonly BridgeService $proxyProviderBridge
    ) {
    }

    /**
     * Provides the proxy connection data, this can be used to make calls via proxy
     *
     * @param ProxyConnectionConfigDto $configDto
     *
     * @return ConnectionDataDto
     *
     * @throws GuzzleException
     * @throws Exception
     */
    public function getConnectionData(ProxyConnectionConfigDto $configDto): ConnectionDataDto
    {
        $request = new GetConnectionDataRequest();
        $request->setProxyInternalId($configDto->getUsedProxyIdentifier());
        $request->setUsage($configDto->getProxyUsage());
        $request->setCountryIsoCode($configDto->getProxyCountryIsoCode());
        $request->setProvider($configDto->getProxyProvider());

        $response = $this->proxyProviderBridge->getConnectionData($request);

        if ($response->getCode() === 404 && EnvReader::isDev()) {
            return ConnectionDataDto::createNotExisting();
        }

        if (!$response->isSuccess()) {
            throw new Exception("Proxy provider returned failure response: {$response->getMessage()}, code: {$response->getCode()}");
        }

        return $response->getConnectionData();
    }

    /**
     * Stores the call information on the proxy provider side
     *
     * @param string $proxyIp
     * @param int    $port
     * @param string $calledUrl
     *
     * @return int
     * @throws GuzzleException
     * @throws Exception
     */
    public function storeCallData(string $proxyIp, int $port, string $calledUrl): int
    {
        $request  = new StoreCallDataRequest();
        $request->setProxyIp($proxyIp);
        $request->setProxyPort($port);
        $request->setUrl($calledUrl);

        $response = $this->proxyProviderBridge->storeCallData($request);

        if (!$response->isSuccess()) {
            throw new Exception("Proxy provider returned failure response: {$response->getMessage()}, code: {$response->getCode()}");
        }

        return $response->getCallId();
    }

    /**
     * updates the call information on the proxy provider side
     *
     * @param int  $callId
     * @param bool $isSuccess
     *
     * @throws GuzzleException
     * @throws Exception
     */
    public function updateCallData(int $callId, bool $isSuccess): void
    {
        $request  = new UpdateCallDataRequest();
        $request->setId($callId);
        $request->setSuccess($isSuccess);

        $response = $this->proxyProviderBridge->updateCallData($request);

        if (!$response->isSuccess()) {
            throw new Exception("Proxy provider returned failure response: {$response->getMessage()}, code: {$response->getCode()}");
        }
    }

}