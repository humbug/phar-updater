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

class GithubStrategy extends AbstractStrategy
{

    const API_URL = 'https://packagist.org/packages/%s.json';

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

    public function getCurrentVersionAvailable(Updater $updater)
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

    public function getThisVersion(Updater $updater)
    {
        return;
    }

    protected function getDownloadUrl(Updater $updater)
    {
        // get link from package data
    }
}
