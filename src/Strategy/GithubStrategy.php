<?php
/**
 * Humbug
 *
 * @category   Humbug
 * @package    Humbug
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/phar-updater/blob/master/LICENSE New BSD License
 *
 * This class is partially patterned after Composer's self-update.
 */

namespace Humbug\SelfUpdate\Strategy;

use Humbug\SelfUpdate\Updater;
use Humbug\SelfUpdate\VersionParser;
use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Exception\InvalidArgumentException;

class GithubStrategy extends AbstractStrategy
{

    const API_URL = 'https://packagist.org/packages/%s.json';

    /**
     * @var string
     */
    private $localVersion;

    /**
     * Download the remote Phar file.
     *
     * @param Updated $updater
     * @return void
     */
    public function download(Updater $updater)
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler(array($updater, 'throwHttpRequestException'));
        $result = humbug_get_contents($updater->getPharUrl());
        restore_error_handler();
        if (false === $result) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s', $updater->getPharUrl()
            ));
        }

        file_put_contents($updater->getTempPharFile(), $result);
    }

    /**
     * Retrieve the current version available remotely.
     *
     * @param Updated $updater
     * @return void
     */
    public function getCurrentRemoteVersion(Updater $updater)
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler(array($updater, 'throwHttpRequestException'));
        $packageUrl = sprintf(self::API_URL, $updater->getPackageName());
        $package = json_decode(humbug_get_contents($packageUrl), true);
        restore_error_handler();

        // check json errors

        $versions = array_keys($package['package']['versions']);
        $versionParser = new VersionParser($version);

        return $versionParser->getMostRecentStable();
    }

    /**
     * Retrieve the current version of the local phar file.
     *
     * @param Updated $updater
     * @return void
     */
    public function getCurrentLocalVersion(Updater $updater)
    {
        return $this->localVersion;
    }

    /**
     * Set version string of the local phar
     *
     * @param string $version
     */
    public function setCurrentLocalVersion($version)
    {
        $this->localVersion = $version;
    }

    protected function getDownloadUrl(Updater $updater)
    {
        // get link from package data
    }
}
