<?php

namespace WebScrapperBundle\Service\Request\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use ProxyProviderBridge\Dto\ConnectionDataDto;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use WebScrapperBundle\Bundle\ProxyProviderService;
use WebScrapperBundle\DTO\ProxyConnectionConfigDto;

/**
 * Service for handling standard request via POST / GET etc.
 */
class GuzzleService implements GuzzleInterface
{
    /**
     * @var Client $client
     */
    private Client $client;

    /**
     * @var array $jsonBody
     */
    private array $jsonBody = [];

    /**
     * @var array $headers
     */
    private array $headers = [];

    /**
     * @var array|int[] $defaultOptions
     */
    private readonly array $defaultOptions;

    /**
     * @var bool $isWithProxy
     */
    private bool $isWithProxy = false;

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
     * Headers to be used with every connection
     *
     * @var array
     */
    readonly private array $defaultHeaders;

    /**
     * The original guzzle configuration, like it's created normally
     *
     * @var array
     */
    readonly private array $originalConfiguration;

    /**
     * @var ConnectionDataDto|null
     */
    private ?ConnectionDataDto $usedProxyConnectionData = null;

    /**
     * @var ProxyConnectionConfigDto $proxyConnectionConfig
     */
    private ProxyConnectionConfigDto $proxyConnectionConfig;

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
     * @param bool $isWithProxy
     */
    public function setIsWithProxy(bool $isWithProxy): void
    {
        $this->isWithProxy = $isWithProxy;
    }

    /**
     * Will set headers that are to be merged into original client configured headers
     *
     * @param array $headers
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    /**
     * Will set the body to be attached to the client request
     *
     * @param array $content
     */
    public function setJsonBody(array $content): void
    {
        $this->jsonBody = $content;
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
     * @return ConnectionDataDto|null
     */
    public function getUsedProxyConnectionData(): ?ConnectionDataDto
    {
        return $this->usedProxyConnectionData;
    }

    /**
     * @param ConnectionDataDto|null $usedProxyConnectionData
     */
    public function setUsedProxyConnectionData(?ConnectionDataDto $usedProxyConnectionData): void
    {
        $this->usedProxyConnectionData = $usedProxyConnectionData;
    }

    /**
     * @param Client                $client
     * @param ProxyProviderService  $proxyProviderService
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(
        Client $client,
        private readonly ProxyProviderService $proxyProviderService,
        ParameterBagInterface $parameterBag
    )
    {
        $this->defaultHeaders = [
            GuzzleInterface::HEADER_CONNECTION => GuzzleInterface::CONNECTION_TYPE_CLOSE,
        ];

        $this->defaultOptions = [
            RequestOptions::TIMEOUT         => $parameterBag->get('scrap.config.max_timeout_seconds'),
            RequestOptions::CONNECT_TIMEOUT => $parameterBag->get('scrap.config.max_timeout_seconds'),
        ];

        $this->originalConfiguration = $client->getConfig();
    }

    /**
     * Will perform get request toward provided url
     *
     * @param string $url - url to be called
     * @param array  $options
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function get(string $url, array $options = []): ResponseInterface
    {
        $this->buildClient();

        $optionsMerges = [
            ...$this->defaultOptions,
            ...$options
        ];

        $callId   = $this->preCallHandler($url);
        $response = $this->client->get($url, $optionsMerges);

        $this->postCallHandler($callId);

        return $response;
    }

    /**
     * Will perform post request toward provided url
     *
     * @param string $url - url to be called
     * @param array  $options
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function post(string $url, array $options = []): ResponseInterface
    {
        $this->buildClient();

        $optionsMerges = [
            ...$this->defaultOptions,
            ...$options
        ];

        $callId   = $this->preCallHandler($url);
        $response = $this->client->post($url, $optionsMerges);

        $this->postCallHandler($callId);

        return $response;
    }

    /**
     * Will build guzzle configuration to be used with all calls when using instance of service
     *
     * @return array
     * @throws GuzzleException
     */
    private function buildConfiguration(): array
    {
        $newConfiguration                               = $this->originalConfiguration;
        $newConfiguration[GuzzleInterface::KEY_HEADERS] = [
            ...$this->defaultHeaders,
            ...$this->headers,
        ];

        $newConfiguration[GuzzleInterface::KEY_JSON_REQUEST_BODY] = $this->jsonBody;

        $this->proxyConnectionConfig = new ProxyConnectionConfigDto();
        $this->proxyConnectionConfig->setProxyUsage($this->getProxyUsage());
        $this->proxyConnectionConfig->setUsedProxyIdentifier($this->getUsedProxyIdentifier());
        $this->proxyConnectionConfig->setIsWithProxy($this->isWithProxy);
        $this->proxyConnectionConfig->setProxyCountryIsoCode($this->getProxyCountryIsoCode());
        $this->proxyConnectionConfig->setProxyProvider($this->getProxyProvider());

        if ($this->proxyConnectionConfig->isWithProxy()) {
            $this->usedProxyConnectionData = $this->proxyProviderService->getConnectionData($this->proxyConnectionConfig);
        }

        if ($this->usedProxyConnectionData?->doesExists()) {
            $newConfiguration[GuzzleInterface::PROXY] = $this->usedProxyConnectionData->getProxyString();
        }

        return $newConfiguration;
    }

    /**
     * This must be separated method due to:
     * - proxy connection being re-fetched on each call
     *
     * @throws GuzzleException
     */
    private function buildClient(): void
    {
        $newConfig    = $this->buildConfiguration();
        $this->client = new Client($newConfig);
    }

    /**
     * @param string $url
     *
     * @return int|null
     *
     * @throws GuzzleException
     */
    private function preCallHandler(string $url): ?int
    {
        if (!$this->proxyConnectionConfig->isWithProxy()) {
            return null;
        }

        $callId = null;
        if ($this->usedProxyConnectionData?->doesExists()) {
            $callId = $this->proxyProviderService->storeCallData(
                $this->usedProxyConnectionData->getIp(),
                $this->usedProxyConnectionData->getPort(),
                $url
            );
        }

        return $callId;
    }

    /**
     * @param int|null $callId
     *
     * @throws GuzzleException
     */
    private function postCallHandler(?int $callId): void
    {
        if (!$this->proxyConnectionConfig->isWithProxy() || empty($callId)) {
            return;
        }

        $this->proxyProviderService->updateCallData($callId, true);
    }

}