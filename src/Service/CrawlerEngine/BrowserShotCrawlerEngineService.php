<?php

namespace WebScrapperBundle\Service\CrawlerEngine;

use DOMDocument;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;
use TypeError;
use WebScrapperBundle\Bundle\ProxyProviderService;
use WebScrapperBundle\DTO\CrawlerConfigurationDto;
use WebScrapperBundle\DTO\ProxyConnectionConfigDto;
use WebScrapperBundle\Exception\MissingDependencyException;
use WebScrapperBundle\Service\Analyse\ProtectedWebsiteAnalyser;
use WebScrapperBundle\Service\ScrapEngine\HeadlessChromeScrapEngine;

/**
 * @link https://github.com/spatie/browsershot
 *      - remember about installing dependencies like in readme
 *
 * @requires https://www.npmjs.com/package/puppeteer
 *
 * Generally allows handling normal web scrapping but is not configured for it
 * Should be explicitly used to handle js based pages as it's really slow
 *
 * @keepInMind
 * While this is fine to handle the js based pages etc. some websites still ban the calls due to detection of crawler
 * tool being used etc. (while in GUI based browsers same pages work fine), which means that this engine is not perfect
 * for bypassing the "crawling tool" checks.
 *
 * Will crawl given page to obtain data from it
 *  - {@see Browsershot::delay()}    - allows forcing to wait given amount of time before fetching content
 *  - {@see Browsershot::$timeout()} - tells how much time does the tool have to fetch data until will time out with exception
 *
 * For more {@see CrawlerService::CRAWLER_ENGINE_BROWSERSHOT}
 *
 * Known issues:
 * - Issue 1:
 *   - State: SOLVED
 *   - Description: Failed to launch the browser process!
 *   - Solution: `sudo apt-get install chromium-browser`
 * - Issue 2:
 *   - State: NOT SOLVED
 *   - Description: 30000ms timeout
 * - Issue 3:
 *    - State: SOLVED
 *    - Description: "permission denied, mkdir '/usr/local/lib/node_modules/puppeteer/.local-chromium'"
 *    - Solution: `sudo npm install -g puppeteer --unsafe-perm=true`
 * - Issue 4:
 *    - State: NOT SOLVED
 *    - Description: "permission denied, mkdir '/usr/local/lib/node_modules/puppeteer/.local-chromium'"
 *    - Solution: `sudo npm install -g puppeteer --unsafe-perm=true`
 */
class BrowserShotCrawlerEngineService implements CrawlerEngineServiceInterface
{
    public function __construct(
        private readonly KernelInterface      $kernel,
        private readonly ProxyProviderService $proxyProviderService
    ) {

    }

    /**
     * {@inheritDoc}
     *
     * @param CrawlerConfigurationDto $crawlerConfigurationDto
     *
     * @return Crawler
     * @throws GuzzleException
     * @throws MissingDependencyException
     * @throws Exception
     */
    public function crawl(CrawlerConfigurationDto $crawlerConfigurationDto): Crawler
    {
        throw new \LogicException("This crawler is disabled. It's not used anywhere anyway. If You need it then You have to adjust it");

        $this->denyIfAntiCrawling($crawlerConfigurationDto);

        $browserShot = $this->call($crawlerConfigurationDto);
        $html        = $browserShot->bodyHtml();
        $domDocument = new DOMDocument();
        $domDocument->loadHTML($html);

        $crawler = new Crawler($domDocument, $crawlerConfigurationDto->getUri());
        $crawler->html($html);

        return $crawler;
    }

    /**
     * Perform a call via {@see Browsershot} but return plain string of page source
     *
     * @param CrawlerConfigurationDto $crawlerConfigurationDto
     *
     * @return string
     *
     * @throws GuzzleException
     * @throws MissingDependencyException
     * @throws Exception
     */
    public function getRaw(CrawlerConfigurationDto $crawlerConfigurationDto): string
    {
        throw new \LogicException("This crawler is disabled. It's not used anywhere anyway. If You need it then You have to adjust it");

        $this->denyIfAntiCrawling($crawlerConfigurationDto);

        $browserShot = $this->call($crawlerConfigurationDto);
        $html        = $browserShot->bodyHtml();

        return $html;
    }

    /**
     * Make a call with {@see Browsershot}
     *
     * @param CrawlerConfigurationDto $crawlerConfigurationDto
     *
     * @return Browsershot
     * @throws MissingDependencyException
     * @throws GuzzleException
     * @throws Exception
     */
    private function call(CrawlerConfigurationDto $crawlerConfigurationDto): Browsershot
    {
        $this->validateSystem();

        $proxyConnectionData = null;
        $proxyConfigDto      = ProxyConnectionConfigDto::tryFromCrawlerConfiguration($crawlerConfigurationDto);
        if ($proxyConfigDto->isWithProxy()) {
            $proxyConnectionData = $this->proxyProviderService->getConnectionData($proxyConfigDto);
        }

        if ($proxyConnectionData?->doesExists()) {
            $callId = $this->proxyProviderService->storeCallData(
                $proxyConnectionData->getIp(),
                $proxyConnectionData->getPort(),
                $crawlerConfigurationDto->getUri()
            );
        }

        /**
         * Prevent {@see DOMDocument} from throwing exception (for example due to not well formatted html)
         */
        libxml_use_internal_errors(true);

        try {
            $options = HeadlessChromeScrapEngine::buildUsedOptions(
                HeadlessChromeScrapEngine::getDefaultOptions(),
                $proxyConnectionData
            );

            $browserShot = Browsershot::url($crawlerConfigurationDto->getUri())
                ->waitForFunction(
                    '() => {' . $crawlerConfigurationDto->getJsFunctionToBeCalledAndWaitedForTrueToReturn() . '}',
                    Browsershot::POLLING_REQUEST_ANIMATION_FRAME,
                    200
                )
                ->setChromePath($this->getBrowserPath())
                ->setNodeBinary($this->getNodeBinaryPath())
                ->setNpmBinary($this->getNpmBinaryPath())
                ->setIncludePath('$PATH:/usr/bin')
                ->addChromiumArguments($options);

            if( !empty($crawlerConfigurationDto->getWaitMillisecondBeforeJsContentFetch()) ){
                $browserShot->setDelay($crawlerConfigurationDto->getWaitMillisecondBeforeJsContentFetch());
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

        return $browserShot;
    }

    /**
     * Will check if system is ready to be working with this crawl engine
     *
     * @throws MissingDependencyException
     */
    private function validateSystem(): void
    {
        $result = shell_exec("npm list -g | grep puppeteer");
        if (empty($result)){
            throw new MissingDependencyException("Pupetter is not installed. Call: `sudo npm install -g puppeteer --unsafe-perm=true`");
        }

        if (empty($this->getBrowserPath())) {
            throw new MissingDependencyException("Neither `chrome` nor `chromium` are installed globally or locally. Did You let the puppeteer install the chrome?");
        }
    }

    /**
     * Will return path of the browser to be used:
     * - first the local instance installed with puppeteer will try to be used,
     * - as second normal chrome instance is going to be taken
     *
     * @return string|null
     */
    private function getBrowserPath(): ?string
    {
        $defaultPuppeteerPath = "/usr/local/lib/node_modules/puppeteer/";
        $localBrowserPath     = shell_exec("find {$defaultPuppeteerPath} -name 'google-chrome'");
        if (empty($localBrowserPath)) {
            $localBrowserPath = shell_exec("find {$defaultPuppeteerPath} -name 'chromium'");
        }

        if (!empty($localBrowserPath)) {
            return trim($localBrowserPath);
        }

        $globalBrowserPath = shell_exec("which google-chrome");
        if (empty($globalBrowserPath)) {
            $globalBrowserPath = shell_exec("which chromium");
        }

        if (!empty($globalBrowserPath)) {
            return trim($globalBrowserPath);
        }

        return null;
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    private function getNpmBinaryPath(): string
    {
        $path = exec("which npm");
        if (empty($path)) {
            throw new Exception("Npm binary not found, is it installed?");
        }

        return $path;
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    private function getNodeBinaryPath(): string
    {
        $path = exec("which node");
        if (empty($path)) {
            throw new Exception("Node binary not found, is it installed?");
        }

        return $path;
    }

    /**
     * @param CrawlerConfigurationDto $crawlerConfigurationDto
     *
     * @throws Exception
     */
    private function denyIfAntiCrawling(CrawlerConfigurationDto $crawlerConfigurationDto): void
    {
        if (ProtectedWebsiteAnalyser::isAntiCrawlingDomain($crawlerConfigurationDto->getUri())) {
            throw new Exception("Trying to crawl an url that has cross-domain anti-crawling protection. Unlocking is not supported for this class!");
        }
    }

}