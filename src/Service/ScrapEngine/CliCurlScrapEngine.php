<?php

namespace WebScrapperBundle\Service\ScrapEngine;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use ProxyProviderBridge\Dto\ConnectionDataDto;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use TypeError;
use WebScrapperBundle\Bundle\ProxyProviderService;
use WebScrapperBundle\Constants\UserAgentConstants;
use WebScrapperBundle\DTO\ProxyConnectionConfigDto;
use WebScrapperBundle\Service\Analyse\ProtectedWebsiteAnalyser;
use WebScrapperBundle\Service\Proxy\CallWithUnlockerHandler;
use WebScrapperBundle\Service\ScrapEngine\Traits\ReCallWithUnlockerAwareTrait;
use WebScrapperBundle\Service\UrlHandler;

/**
 * Handles scrapping via raw CURL in CLI
 * {@see RawCurlScrapEngine - it's not the same,
 * I mean... theoretically it is, but sometimes the cli based scrapper provides results while the
 * {@see RawCurlScrapEngine} doesn't, could not figure out why
 */
class CliCurlScrapEngine implements ScrapEngineInterface
{
    use ReCallWithUnlockerAwareTrait;

    private bool $isAntiCrawlingUnlockerUsed = false;

    public function __construct(
        private readonly KernelInterface      $kernel,
        private readonly ProxyProviderService $proxyProviderService
    ) {

    }

    /**
     * {@inheritDoc}
     * Is pre-configured so that it is quick,
     *
     * @return string
     * @throws Exception
     * @throws GuzzleException
     */
    public function scrap(string $url, array $configurationData = []): string
    {
        $userAgent = $configurationData[ScrapEngineInterface::CONFIGURATION_USER_AGENT] ?? UserAgentConstants::CHROME_43;
        $headers   = $configurationData[ScrapEngineInterface::CONFIGURATION_HEADERS] ?? [];
        $method    = $configurationData[ScrapEngineInterface::CONFIGURATION_METHOD] ?? Request::METHOD_GET;
        $body      = $configurationData[ScrapEngineInterface::CONFIGURATION_BODY] ?? null;

        if (ProtectedWebsiteAnalyser::isAntiCrawlingDomain($url)) {
            $configurationData = CallWithUnlockerHandler::setProxyConnectionForConfigurationArray($configurationData);
        }

        $proxyConfigDto = ProxyConnectionConfigDto::tryFromArray($configurationData);

        $proxyConnectionDto = null;
        if ($proxyConfigDto->isWithProxy()) {
            $proxyConnectionDto = $this->proxyProviderService->getConnectionData($proxyConfigDto);
        }

        if ($proxyConnectionDto?->doesExists()) {
            $callId = $this->proxyProviderService->storeCallData(
                $proxyConnectionDto->getIp(),
                $proxyConnectionDto->getPort(),
                $url
            );
        }

        try {
            $calledCommand = $this->buildCommand($url, $headers, $userAgent, $method, $body, $proxyConnectionDto);
            $result        = shell_exec($calledCommand);

            if (empty($result)) {
                throw new Exception("Something went wrong when executing the cli command: {$calledCommand}.");
            }
        } catch (Exception|TypeError $e) {
            if ($proxyConfigDto->isWithProxy() && !empty($callId)) {
                $this->proxyProviderService->updateCallData($callId, false);
            }

            throw $e;
        }

        if ($proxyConfigDto->isWithProxy() && !empty($callId)) {
            $this->proxyProviderService->updateCallData($callId, true);
        }

        if (ProtectedWebsiteAnalyser::isAntiCrawling(new Crawler($result), [], $url)) {
            $result = $this->reCallForUnlockerIfNeeded($url, $configurationData, $proxyConfigDto, $result);
        }

        return $result;
    }

    /**
     * Will build the called shell command
     *
     * @param string                 $url
     * @param array                  $headers
     * @param string                 $userAgent
     * @param string                 $method
     * @param array|null             $body
     * @param ConnectionDataDto|null $proxyConnectionDto
     *
     * @return string
     */
    private function buildCommand(string $url, array $headers, string $userAgent, string $method, ?array $body = null, ?ConnectionDataDto $proxyConnectionDto = null): string
    {
        $encodedUrl = UrlHandler::queryParamsToUtf8($url);

        // the -sb ensures that only response content is being returned, no headers, no curling data etc.
        $base              = "curl -sL ";
        $timeoutPart       = " -m " . $this->kernel->getContainer()->getParameter('scrap.config.max_timeout_seconds') . " ";
        $allowInsecurePart = " --insecure ";

        $headersString = "";
        $agentString   = "";
        $proxy         = "";

        $normalisedMethod = strtoupper($method);
        $base             .= " -X {$normalisedMethod}";

        foreach ($headers as $name => $value) {
            $headersString .= " -H '{$name}:{$value}' ";
        }

        if (!empty($body)) {
            $json = json_encode($body, true);
            $base .= " -d '{$json}'";
        }

        if (!empty($userAgent)) {
            $agentString .= " -H 'user-agent: {$userAgent}' ";
        }

        if ($proxyConnectionDto?->doesExists()) {
            $proxy .= " -x {$proxyConnectionDto->getProxyString()} ";
        }

        return $base . $timeoutPart . $allowInsecurePart .  $headersString . $agentString . $proxy . "'{$encodedUrl}'";
    }

}