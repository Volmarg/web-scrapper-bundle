<?php

namespace WebScrapperBundle\Constants;

/**
 * Stores variety of user agents, reasons:
 * - re-usage,
 */
class UserAgentConstants
{
    public const CHROME_85  = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.121 Safari/537.36';
    public const CHROME_43  = 'Mozilla/5.0 (compatible; Windows NT 6.1; Catchpoint) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.81 Safari/537.36';
    public const CHROME_101 = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.64 Safari/537.36';
    public const CHROME_114 = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36';

    public const FIREFOX_85 = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:85.0) Gecko/20100101 Firefox/85.0';
    public const FIREFOX_24 = 'Mozilla/5.0 (X11; Linux i686; rv:24.0) Gecko/20100101 Firefox/24.0 DejaClick/2.5.0.11';

    public const INSOMNIA   = 'insomnia/2021.7.2'; // tool like postman
    public const POSTMAN_7_32_3 = "PostmanRuntime/7.32.3";
}