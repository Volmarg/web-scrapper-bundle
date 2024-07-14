<?php

namespace WebScrapperBundle\Service\Analyse;

use Exception;
use Symfony\Component\DomCrawler\Crawler;
use TypeError;

/**
 * Will check if website is hiding behind captcha / cloudflare etc.
 */
class ProtectedWebsiteAnalyser
{
    /**
     * It's known that these domain got anti-crawling protection
     * No need for exact match (however it's recommended),
     * Partial matches will also be excluded {@see str_contains()}
     *
     * BE VERY CAREFUL WHEN CHANGING THIS!
     * - it was decided that if call will be made toward given domain then it's INSTANTLY getting called
     *   with unlocker proxy (which is expensive), the rules such as "can recall with unlocker" are ignored,
     *   here because it makes no sense to use that and waste money on doing "data-center" call, just to get
     *   the content / headers first, to then decide that it should actually be called with unlocker.
     *   While in fact it's already known that this domain DOES need unlocker call
     *
     */
    private const DOMAINS_WITH_ANTI_CRAWLING = [
        "indeed.com",
        "pracuj.pl",
        "es.talent.com",
        "infojobs.net.esp",
        "crunchbase.com",
        "jooble.org",
    ];

    // these regexps are detecting the anti-crawling strings in page content
    private const ANTI_CRAWLING_REGEXP_PATTERNS_ON_PAGE = [
        // generic
        "suspicious activity",
        "bot in network",

        // cloudflare
        "challenge-error-title",
    ];

    // if these keys are present then it means that page is anti-crawling protected
    private const ANTI_CRAWLING_HEADER_KEYS = [
        // Cloudflare specific: https://developers.cloudflare.com/fundamentals/reference/http-request-headers/
        "cf-connecting-ip",
        "cf-ew-via",
        "cf-pseudo-ipv4",
        "true-client-ip",
        "cf-ray",
        "cf-ipcountry",
        "cf-visitor",
        "cf-sorker",
    ];

    // if given header key with value exists then it's anti-crawling protected
    private const ANTI_CRAWLER_HEADERS_WITH_VALUES = [

    ];

    /**
     * Performs simple check toward the target url, if its domain is on the list of domains known
     * for anti-crawling measurements
     *
     * @param string $url
     *
     * @return bool
     */
    public static function isAntiCrawlingDomain(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        foreach (self::DOMAINS_WITH_ANTI_CRAWLING as $domain) {
            if (empty($host)) {
                continue;
            }

            if (mb_strtolower($domain) === mb_strtolower($host)) {
                return true;
            }
        }

        // looping again because host exclusion is stricter so should not be combined with partial match
        foreach (self::DOMAINS_WITH_ANTI_CRAWLING as $domain) {
            if (empty($host)) {
                continue;
            }

            if (str_contains($host, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks the:
     * - body content,
     * - headers,
     * - url
     *
     * for anti-crawling protections, or anything that can disrupt crawling
     *
     * Order of calls matters, going with faster one first, then slower ones
     *
     * @param Crawler $crawler
     * @param array   $headers
     * @param string  $url
     *
     * @return bool
     */
    public static function isAntiCrawling(Crawler $crawler, array $headers, string $url): bool
    {
        if (self::isAntiCrawlingDomain($url)) {
            return true;
        }

        foreach ($headers as $key => $value) {
            if (in_array(mb_strtolower($key), self::ANTI_CRAWLING_HEADER_KEYS)) {
                return true;
            }

            foreach (self::ANTI_CRAWLER_HEADERS_WITH_VALUES as $knownKey => $knownValue) {
                if (
                        (mb_strtolower($key) === mb_strtolower($knownKey))
                    ||  ($knownValue == $value)
                    ||  (mb_strtolower($knownValue) === mb_strtolower($value))
                ) {
                    return true;
                }
            }
        }

        foreach (self::ANTI_CRAWLING_REGEXP_PATTERNS_ON_PAGE as $regexp) {
            try {
                if (preg_match("#{$regexp}#", $crawler->html())) {
                    return true;
                }
            } catch (Exception|TypeError) {
                // no rethrow because it can happen that page was not crawled properly, html is missing, and that's actually ok
                continue;
            }
        }

        return false;
    }
}