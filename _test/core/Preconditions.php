<?php

namespace dokuwiki\plugin\gitbacked\test\core;

use Exception;

/**
 * Helper class to check pre conditions.
 */
class Preconditions
{
    /**
     * Check if an environment variable is defined and not empty.
     *
     * @access  public
     * @param   string $envVarName the environment var name
     * @throws  Exception
     */
    public static function envIsNotEmpty($envVarName): void
    {
        if (empty(getenv($envVarName))) {
            throw new Exception('Environment variable ' . $envVarName . ' is NOT set!');
        }
    }
}
