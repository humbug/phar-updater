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

abstract class ShaStrategyAbstract implements StrategyInterface
{

    /** @private */
    const SUPPORTED_SCHEMES = [
        'http',
        'https',
        'file',
    ];

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
        return (
            filter_var($url, FILTER_VALIDATE_URL)
            && in_array(parse_url($url, PHP_URL_SCHEME), self::SUPPORTED_SCHEMES)
        );
    }
}
