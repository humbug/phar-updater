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
use Humbug\SelfUpdate\Exception\HttpRequestException;

class GithubStrategy extends AbstractStrategy
{

    const API_URL = 'https://api.github.com/';

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


        $version = humbug_get_contents($updater->getVersionUrl());

        
        restore_error_handler();
        if (false === $version) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s', $updater->getVersionUrl()
            ));
        }
        if (empty($version)) {
            throw new HttpRequestException(
                'Version request returned empty response.'
            );
        }
        if (!preg_match('%^[a-z0-9]{40}%', $version, $matches)) {
            throw new HttpRequestException(
                'Version request returned incorrectly formatted response.'
            );
        }

        return $matches[0];
    }

}