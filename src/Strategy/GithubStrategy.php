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
use Humbug\SelfUpdate\Exception\JsonParsingException;

class GithubStrategy implements StrategyInterface
{
    const API_URL = 'https://packagist.org/packages/%s.json';

    const STABLE = 'stable';

    const UNSTABLE = 'unstable';

    const ANY = 'any';

    /**
     * @var string
     */
    private $localVersion;

    /**
     * @var string
     */
    private $remoteVersion;

    /**
     * @var string
     */
    private $remoteUrl;

    /**
     * @var string
     */
    private $pharName;

    /**
     * @var string
     */
    private $packageName;

    /**
     * @var string
     */
    private $stability = self::STABLE;

    /**
     * @var boolean
     */
    private $allowMajor = false;

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
        $result = file_get_contents($this->remoteUrl);
        restore_error_handler();
        if (false === $result) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s', $this->remoteUrl
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
        $packageUrl = $this->getApiUrl();
        $package = json_decode(file_get_contents($packageUrl), true);
        restore_error_handler();

        if (null === $package || json_last_error() !== JSON_ERROR_NONE) {
            throw new JsonParsingException(
                'Error parsing JSON package data'
                . (function_exists('json_last_error_msg') ? ': ' . json_last_error_msg() : '')
            );
        }

        $versions = array_keys($package['package']['versions']);

        if (false === $this->allowMajor) {
            $versions = $this->filterByLocalMajorVersion($versions);
        }

        $versionParser = new VersionParser($versions);
        if ($this->getStability() === self::STABLE) {
            $this->remoteVersion = $versionParser->getMostRecentStable();
        } elseif ($this->getStability() === self::UNSTABLE) {
            $this->remoteVersion = $versionParser->getMostRecentUnstable();
        } else {
            $this->remoteVersion = $versionParser->getMostRecentAll();
        }

        /**
         * Setup remote URL if there's an actual version to download
         */
        if (!empty($this->remoteVersion)) {
            $this->remoteUrl = $this->getDownloadUrl($package);
        }

        return $this->remoteVersion;
    }

    /**
     * Retrieve the current version of the local phar file.
     *
     * @param Updater $updater
     * @return string
     */
    public function getCurrentLocalVersion(Updater $updater)
    {
        return $this->localVersion;
    }

    /**
     * Set version string of the local phar
     *
     * @param string $version
     * @return self
     */
    public function setCurrentLocalVersion($version)
    {
        $this->localVersion = $version;
        return $this;
    }

    /**
     * Set Package name
     *
     * @param string $name
     * @return self
     */
    public function setPackageName($name)
    {
        $this->packageName = $name;
        return $this;
    }

    /**
     * Get Package name
     *
     * @return string
     */
    public function getPackageName()
    {
        return $this->packageName;
    }

    /**
     * Set phar file's name
     *
     * @param string $name
     * @return self
     */
    public function setPharName($name)
    {
        $this->pharName = $name;
        return $this;
    }

    /**
     * Get phar file's name
     *
     * @return string
     */
    public function getPharName()
    {
        return $this->pharName;
    }

    /**
     * Set target stability
     *
     * @param string $stability
     * @return self
     */
    public function setStability($stability)
    {
        if ($stability !== self::STABLE && $stability !== self::UNSTABLE && $stability !== self::ANY) {
            throw new InvalidArgumentException(
                'Invalid stability value. Must be one of "stable", "unstable" or "any".'
            );
        }
        $this->stability = $stability;
        return $this;
    }

    /**
     * Get target stability
     *
     * @return string
     */
    public function getStability()
    {
        return $this->stability;
    }

    /**
     * @return self
     */
    public function allowMajorVersionUpdates()
    {
        $this->allowMajor = true;
        return $this;
    }

    /**
     * @return string
     */
    protected function getApiUrl()
    {
        return sprintf(self::API_URL, $this->getPackageName());
    }

    /**
     * @param  array  $package
     * @return string
     */
    protected function getDownloadUrl(array $package)
    {
        $baseUrl = preg_replace(
            '{\.git$}',
            '',
            $package['package']['versions'][$this->remoteVersion]['source']['url']
        );
        $downloadUrl = sprintf(
            '%s/releases/download/%s/%s',
            $baseUrl,
            $this->remoteVersion,
            $this->getPharName()
        );
        return $downloadUrl;
    }

    /**
     * Filter a list of versions to those that match the current local version.
     *
     * @param string[] $versions
     * @return string[]
     */
    private function filterByLocalMajorVersion(array $versions)
    {
        list($localMajorVersion, ) = explode('.', $this->localVersion, 2);

        return array_filter($versions, function ($version) use ($localMajorVersion) {
            list($majorVersion, ) = explode('.', $version, 2);
            return $majorVersion === $localMajorVersion;
        });
    }
}
