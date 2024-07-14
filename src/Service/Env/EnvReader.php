<?php

namespace WebScrapperBundle\Service\Env;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Handles reading operations from .env file
 */
class EnvReader extends AbstractController
{

    const VAR_APP_ENV = "APP_ENV";
    const APP_ENV_MODE_DEV = "dev";

    /**
     * Check if the project runs on the development system
     *
     * @return bool
     */
    public static function isDev(): bool
    {
        return ($_ENV[self::VAR_APP_ENV] === self::APP_ENV_MODE_DEV);
    }

    /**
     * Returns the current environment in which the app runs in
     *
     * @return string
     */
    public static function getEnvironment(): string
    {
        return $_ENV[self::VAR_APP_ENV];
    }

}
