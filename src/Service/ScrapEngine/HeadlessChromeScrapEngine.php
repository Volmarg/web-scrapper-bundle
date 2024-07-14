<?php

namespace WebScrapperBundle\Service\ScrapEngine;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use ProxyProviderBridge\Dto\ConnectionDataDto;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use TypeError;
use WebScrapperBundle\Bundle\ProxyProviderService;
use WebScrapperBundle\DTO\ProxyConnectionConfigDto;
use WebScrapperBundle\Exception\MissingDependencyException;
use WebScrapperBundle\Service\Analyse\ProtectedWebsiteAnalyser;
use WebScrapperBundle\Service\CrawlerService;
use WebScrapperBundle\Service\Env\EnvReader;

/**
 * Handles providing the page content by using the "Headless Chrome" via CLI calls
 * Available params:
 * - {@link https://github.com/GoogleChrome/chrome-launcher/blob/main/docs/chrome-flags-for-tools.md}
 *
 * If the command called in cli is not returning anything, the remove the `log` based commands to see errors in CLI.
 *
 * **Known, NOT solve-able issues**
 * - `dump-dom` sometimes is not returning anything
 *     - that's `chrome` issue, see {@link https://stackoverflow.com/questions/57490271/problem-with-chrome-headless-from-cli-empty-pdf-and-error-failed-to-serialize}
 *     - also {@link https://bugs.chromium.org/p/chromium/issues/detail?id=993686}
 *
 * >WARNING< Headless Chrome does NOT support proxy with authentication!
 */
class HeadlessChromeScrapEngine implements HeadlessBrowserInterface, HeadlessChromeBrowserInterface
{

    public function __construct(
        private readonly LoggerInterface      $logger,
        private readonly KernelInterface      $kernel,
        private readonly ProxyProviderService $proxyProviderService
    ){}

    /**
     * This option is responsible for providing the page output in cli,
     * Downside:
     * - if page returns json content then it's wrapped inside html tags anyway (these got to be stripped later on)
     */
    private const OPTION_DUMP_DOM ="dump-dom";

    private const FAILED_SCRAPPING_RESULT_EMPTY_DATA = "<html><head></head><body></body></html>";

    /**
     * {@inheritDoc}
     *
     * - Skipping the unlocker is a must here, because unlocker-proxy based call always counts as "unlocker usage", no
     *   matter if the page needed to be bypassed, so using it with chrome would generate a lot of expenses as chrome
     *   fetches all the assets etc. which further means that each asset fetch would count to unlocker-proxy call.
     *   > This is valid only for PROD. On Dev some services must be crawled with Headless Browser, for example
     *     CrunchBase. The dev is still not allowed to use unlocker-proxy tho.
     *
     * - The "dump dom" and "url" MUST be always last in the called command, else things are breaking
     *
     * @param string $url
     * @param array  $configurationData
     *
     * @return string
     * @throws GuzzleException
     * @throws Exception
     */
    public function scrap(string $url, array $configurationData = []): string
    {
        $callId              = null;
        $proxyConnectionDto  = null;

        if (ProtectedWebsiteAnalyser::isAntiCrawlingDomain($url) && !EnvReader::isDev()) {
            throw new Exception("Trying to crawl an url that has cross-domain anti-crawling protection. Unlocking is not supported for this class!");
        }

        $proxyConfigDto = ProxyConnectionConfigDto::tryFromArray($configurationData);
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
            $this->validateSystem();

            $appendedOptions = "";
            $options         = $this->buildUsedOptions($configurationData, $proxyConnectionDto);
            foreach ($options as $option => $value) {
                $appendedOptions .= " --{$option}" . (!empty($value) ? "={$value}" : "");
            }

            $this->logger->debug("Crawling {$url} with headless Chrome");
            $executedCommand = "google-chrome {$appendedOptions} --" . self::OPTION_DUMP_DOM . " '{$url}'";
            $result          = shell_exec($executedCommand);
            if (empty($result)) {
                throw new Exception("Something went wrong while executing shell command: {$executedCommand}");
            }

            if (trim($result) === self::FAILED_SCRAPPING_RESULT_EMPTY_DATA) {
                throw new Exception("Could not get results for url: {$url}. Body is empty. Maybe again proxy issues?");
            }
        } catch (Exception|TypeError $e) {
            if (!empty($callId)) {
                $this->proxyProviderService->updateCallData($callId, false);
            }
            throw $e;
        }

        if (!empty($callId)) {
            $this->proxyProviderService->updateCallData($callId, true);
        }

        return $result;
    }

    /**
     * Will return array of options
     *
     * @return array
     */
    public static function getDefaultOptions(): array
    {
        /**
         * Got totally no idea what these are but were added after some research on internet of how to
         * "bypass" the "access denied".
         *
         * - timeout: seems like there is no options to control how long browser can try to load the page before it
         *   crashes / quits. There is "--timeout" but it works differently, it's not "max timeout" it's "please wait
         *   this long before passing the result to dump-dom" - which is undesired. Yes it would solve the problems
         *   (if there would be such), but on the other hand each call "if finished earlier" would just be stale.
         */
        return [
            "headless"                           => null,
            "no-sandbox"                         => null,
            "disable-setuid-sandbox"             => null,
            "ignore-certifcate-errors"           => null,
            "ignore-certifcate-errors-spki-list" => null,
            "blink-settings=imagesEnabled"       => false,
            "hide-scrollbars"                    => null,
            "mute-audio"                         => null,
            "disable-gl-drawing-for-tests"       => null,
            "disable-canvas-aa"                  => null,
            "disable-2d-canvas-clip-aa"          => null,
            "disable-dev-shm-usage"              => null,
            "no-zygote"                          => null,
            "use-gl"                             => "desktop",
            "disable-infobars"                   => null,
            "disable-breakpad"                   => null,
            "window-size"                        => "10,10",
            "disable-gpu"                        => null,
            "disable-software-rasterizer"        => null,
            "allow-running-insecure-content"     => null,
            "disable-extensions"                 => null,
            "log-level"                          => 3,     // only `fatal`, disabled only part of logs
            "enable-logging"                     => false, // disables only part of logs (other than from log-level)
        ];
    }

    /**
     * Will return the set of the options used by the headless chrome scrap engine
     *
     * @param array                  $configurationData
     * @param ConnectionDataDto|null $proxyConnectionDto
     *
     * @return array
     */
    public static function buildUsedOptions(array $configurationData, ?ConnectionDataDto $proxyConnectionDto): array
    {
        $usedOptions = self::getDefaultOptions();

        /**
         * This is a must, else some pages detect that headless chrome uses
         * "Headless browser, and they block such calls, this syntax of quotes IS NEEDED
         */
        $userAgent                 = $configurationData[ScrapEngineInterface::CONFIGURATION_USER_AGENT] ?? CrawlerService::DEFAULT_USER_AGENT;
        $usedOptions["user-agent"] = "'{$userAgent}'";

        # These are supposed to help with waiting for ajax calls
        if (isset($configurationData[HeadlessChromeBrowserInterface::CONFIG_USE_VIRTUAL_DOM_BUDGET])) {
            $usedOptions["run-all-compositor-stages-before-draw"] = null; # has to be combined with `virtual-time-budget`
            $usedOptions['virtual-time-budget']                   = HeadlessChromeBrowserInterface::CONFIG_VIRTUAL_TIME_BUDGET_DEFAULT_VALUE;
        }

        if ($proxyConnectionDto?->doesExists()) {
            $usedOptions['proxy-server'] = "{$proxyConnectionDto->getProxyString(false)}";

            /**
             * These extra headers were added due to having issues with getting valid content when using proxy
             * the other problem is that when calling the search engines such as google it produces tones of request
             * as chrome tries to verify some urls on page, thus special rules are implemented directly in the
             * ProxyManager.
             */
            $usedOptions['remote-allow-origins']               = "*";
            $usedOptions['allow-insecure-localhost']           = null;
            $usedOptions['disable-content-security-policy']    = null;
            $usedOptions['ignore-https-errors']                = null;
            $usedOptions['accept-insecure-certs']              = null;
            $usedOptions['ignore-ssl-errors']                  = null;
            $usedOptions['ignore-certificate-errors']          = null;
            $usedOptions['ignore-certifcate-errors-spki-list'] = null;
        }else{
            $usedOptions['proxy-bypass-list'] = "*";
            $usedOptions['proxy-server']      = 'direct://';
        }

        return $usedOptions;
    }

    /**
     * Validates the environment on which this scrap engine logic will get executed
     *
     * @return void
     * @throws MissingDependencyException
     */
    private function validateSystem(): void
    {
        $checkResult = shell_exec("which google-chrome");
        if (empty($checkResult)) {
            throw new MissingDependencyException("`google-chrome` executable binary was not found! Did You installed chromium?");
        }
    }
}