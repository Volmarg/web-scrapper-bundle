<?php

namespace WebScrapperBundle\Service\CrawlerEngine\Goutte;

use WebScrapperBundle\Service\CrawlerEngine\CrawlerEngineServiceInterface;

/**
 * Describes explicitly the {@see GoutteCrawlerEngineService}
 */
interface GoutteCrawlerInterface extends CrawlerEngineServiceInterface
{
    /**
     * checks if the SSL should be validated or not (for example if is set / expired etc.) (set false to ignore the errors)
     * @link https://stackoverflow.com/questions/37324500/setting-curl-parameters-for-fabpot-goutte-client
     *
     * Supported by:
     *- {@see GoutteCrawlerEngineService} {@see HttpClient}
     */
    const OPTION_SSL_VERIFY_PEER = "verify_peer";

    /**
     * Related to the error: "no alternative certificate subject name matches target" (set false to ignore this error)
     */
    const OPTION_SSL_VERIFY_HOST = "verify_host";

    /**
     * Has something to do with error "tls_process_ske_dhe:dh key too small" ("DEFAULT@SECLEVEL=1" seems to solve it)
     * @link https://stackoverflow.com/questions/63235805/curl-openssl-error-141a318a-tls-process-ske-dhedh-key-too-small
     *
     * Supported by:
     * - {@see GoutteCrawlerEngineService} {@see HttpClient}
     */
    const OPTION_CIPHERS = "ciphers";

    /**
     * How long should the connection be kept alive while waiting for response until being force disconnected
     * This is very helpful for some weird cases where target server has some protections or misconfigurations
     * which choke the connections and let it hang even up to 1min in some edge cases
     *
     * Keep in mind that the parameter is very dependent on the internet connection.
     * If internet connection is very slow then setting low number will make almost everything timeout since
     * the download time will be longer on lower speed
     *
     * Supported by:
     * - {@see GoutteCrawlerEngineService} {@see HttpClient}
     */
    const OPTION_MAX_DURATION = "max_duration";

    /**
     * Will forward the request to given proxy,
     * - example: `http://xx.xx.xx.xx:8080`
     */
    const OPTION_PROXY = "proxy";

}