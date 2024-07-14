<?php

namespace WebScrapperBundle\Service\ScrapEngine;

/**
 * Interface specially for headless chrome scrap engine
 */
interface HeadlessChromeBrowserInterface
{
    public const CONFIG_USE_VIRTUAL_DOM_BUDGET            = "use-virtual-dom-budget";
    public const CONFIG_VIRTUAL_TIME_BUDGET_DEFAULT_VALUE = 20000; // probably milliseconds
}