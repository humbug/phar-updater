<?php
/**
 * Humbug
 *
 * @category   Humbug
 * @package    Humbug
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/phar-updater/blob/master/LICENSE New BSD License
 *
 * This is partially patterned after Composer's self-update.
 */

namespace Humbug\SelfUpdate\Strategy;

use Humbug\SelfUpdate\Updater;

interface StrategyInterface
{

    /**
     * Download the remote Phar file.
     *
     * @param Updater $updater
     * @return void
     */
    public function download(Updater $updater);

    /**
     * Retrieve the current version available remotely.
     *
     * @param Updater $updater
     * @return string|bool
     */
    public function getCurrentRemoteVersion(Updater $updater);

    /**
     * Retrieve the current version of the local phar file.
     *
     * @param Updater $updater
     * @return string
     */
    public function getCurrentLocalVersion(Updater $updater);
}
