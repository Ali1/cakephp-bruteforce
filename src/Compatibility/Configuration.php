<?php
declare(strict_types=1);

namespace Ali1\BruteForceShield;

use Bruteforce\Configuration as BruteforceConfiguration;

/**
 * Backwards-compatible name for applications upgrading from BruteForceShield.
 *
 * @deprecated Use \Bruteforce\Configuration.
 */
class Configuration extends BruteforceConfiguration
{
}
