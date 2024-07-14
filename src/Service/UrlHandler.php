<?php

namespace WebScrapperBundle\Service;

use WebScrapperBundle\Service\ScrapEngine\CliCurlScrapEngine;

class UrlHandler
{

    /**
     * In some cases (For example in {@see CliCurlScrapEngine}) the query params must be necoded
     *
     * @param string $url
     *
     * @return string
     */
    public static function queryParamsToUtf8(string $url): string
    {
        $urlPartials = parse_url($url);
        $queryArray  = $urlPartials['query'] ?? null;
        if (empty($queryArray)) {
            return $url;
        }

        $regex          = "#(?<KEY>[^=]*)=(?<VALUE>(.*))#";
        $queryParamsStr = explode("&", $queryArray);

        $encodedPartialsArr = [];
        foreach ($queryParamsStr as $paramStr) {
            preg_match($regex, $paramStr, $matches);
            $key   = $matches['KEY'];
            $value = ($matches['VALUE']);

            $convertedValue = iconv(mb_detect_encoding($value, mb_detect_order(), true), "UTF-8", $value);

            $encodedPartialsArr[] = "{$key}={$convertedValue}";
        }

        $encodedQueryString = implode("&", $encodedPartialsArr);

        $scheme = ($urlPartials['scheme'] ?? '');
        $scheme = (empty($scheme) ? '' : "{$scheme}://");

        $host   = $urlPartials['host'];
        $path   = ($urlPartials['path'] ?? '');
        $query  = "?{$encodedQueryString}";

        $gluedUrl = $scheme . $host . $path . $query;

        return $gluedUrl;
    }

}