<?php

namespace WebScrapperBundle\DTO\ScrapEngine;

class CurlResponse
{
    public function __construct(
        private readonly string $body,
        private readonly array $headers,
    )  {}

    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

}