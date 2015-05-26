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
use Humbug\SelfUpdate\Exception\InvalidArgumentException;

class ShaStrategy implements StrategyInterface
{

    /**
     * @var string
     */
    protected $versionUrl;

    /**
     * @var string
     */
    protected $pharUrl;

    /**
     * Download the remote Phar file.
     *
     * @param Updater $updater
     * @return void
     */
    public function download(Updater $updater)
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler(array($updater, 'throwHttpRequestException'));
        $result = humbug_get_contents($this->getPharUrl());
        restore_error_handler();
        if (false === $result) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s', $this->getPharUrl()
            ));
        }

        file_put_contents($updater->getTempPharFile(), $result);
    }

    /**
     * Retrieve the current version available remotely.
     *
     * @param Updater $updater
     * @return string|bool
     */
    public function getCurrentRemoteVersion(Updater $updater)
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler(array($updater, 'throwHttpRequestException'));
        $version = humbug_get_contents($this->getVersionUrl());
        restore_error_handler();
        if (false === $version) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s', $this->getVersionUrl()
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

    /**
     * Retrieve the current version of the local phar file.
     *
     * @param Updater $updater
     * @return string
     */
    public function getCurrentLocalVersion(Updater $updater)
    {
        return sha1_file($updater->getLocalPharFile());
    }

    /**
     * Set URL to phar file
     *
     * @param string $url
     */
    public function setPharUrl($url)
    {
        if (!$this->validateAllowedUrl($url)) {
            throw new InvalidArgumentException(
                sprintf('Invalid url passed as argument: %s.', $url)
            );
        }
        $this->pharUrl = $url;
    }

    /**
     * Get URL for phar file
     *
     * @return string
     */
    public function getPharUrl()
    {
        return $this->pharUrl;
    }

    /**
     * Set URL to version file
     *
     * @param string $url
     */
    public function setVersionUrl($url)
    {
        if (!$this->validateAllowedUrl($url)) {
            throw new InvalidArgumentException(
                sprintf('Invalid url passed as argument: %s.', $url)
            );
        }
        $this->versionUrl = $url;
    }

    /**
     * Get URL for version file
     *
     * @return string
     */
    public function getVersionUrl()
    {
        return $this->versionUrl;
    }

    protected function validateAllowedUrl($url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL)
        && in_array(parse_url($url, PHP_URL_SCHEME), array('http', 'https', 'file'))) {
            return true;
        }
        return false;
    }
}
