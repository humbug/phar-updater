<?php
/**
 * Humbug
 *
 * @category   Humbug
 * @package    Humbug
 * @copyright  Copyright (c) 2017 Patrick Dawkins
 * @license    https://github.com/padraic/phar-updater/blob/master/LICENSE New BSD License
 *
 */
namespace Humbug\SelfUpdate\Strategy;

use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Exception\JsonParsingException;
use Humbug\SelfUpdate\Updater;
use Humbug\SelfUpdate\VersionParser;
use Humbug\SelfUpdate\Exception\RuntimeException;

final class ManifestStrategy implements StrategyInterface
{
    const SHA256 = 'sha256';

    const SHA1 = 'sha1';

    const STABLE = 'stable';

    const UNSTABLE = 'unstable';

    const ANY = 'any';

    /**
     * @var array
     */
    private $requiredKeys = array(self::SHA256, 'version', 'url');

    /**
     * @var string
     */
    private $hashAlgo = self::SHA256;

    /**
     * @var string
     */
    private $manifestUrl;

    /**
     * @var array
     */
    private $manifest;

    /**
     * @var array
     */
    private $availableVersions;

    /**
     * @var string
     */
    private $localVersion;

    /**
     * @var bool
     */
    private $allowMajor = false;

    /**
     * @var bool
     */
    private $allowUnstable = false;

    /**
     * @var bool
     */
    private $requireUnstable = false;

    /**
     * @var int
     */
    private $manifestTimeout = 60;

    /**
     * @var int
     */
    private $downloadTimeout = 60;

    /**
     * @var bool
     */
    private $ignorePhpReq = false;

    /**
     * Set version string of the local phar
     *
     * @param string $version
     */
    public function setCurrentLocalVersion($version)
    {
        $this->localVersion = $version;
        return $this;
    }

    /**
     * @param int $downloadTimeout
     * @return  self
     */
    public function setDownloadTimeout($downloadTimeout)
    {
        $this->downloadTimeout = $downloadTimeout;
        return $this;
    }

    /**
     * @param int $manifestTimeout
     * @return  self
     */
    public function setManifestTimeout($manifestTimeout)
    {
        $this->manifestTimeout = $manifestTimeout;
        return $this;
    }

    public function useSha1()
    {
        $this->requiredKeys[0] = self::SHA1;
        $this->hashAlgo = self::SHA1;
    }

    /**
     * If set, ignores any restrictions based on currently running PHP version.
     * @return  self
     */
    public function ignorePhpRequirements()
    {
        $this->ignorePhpReq = true;
        return $this;
    }

    /**
     * If set, ignores any restrictions based on currently running PHP version.
     * @return  self
     */
    public function allowMajorVersionUpdates()
    {
        $this->allowMajor = true;
        return $this;
    }

    /**
     * Set target stability
     *
     * @param string $stability
     */
    public function setStability($stability)
    {
        if ($stability !== self::STABLE && $stability !== self::UNSTABLE && $stability !== self::ANY) {
            throw new InvalidArgumentException(
                'Invalid stability value. Must be one of "stable", "unstable"'
            );
        }
        
        switch ($stability) {
            case self::ANY:
                $this->allowUnstableVersionUpdates();
                break;

            case self::UNSTABLE:
                $this->requireUnstable = true;
                break;
            
            default:
                break;
        }
    }

    /**
     * If set, ignores any restrictions based on currently running PHP version.
     * @return  self
     */
    public function allowUnstableVersionUpdates()
    {
        $this->allowUnstable = true;
        return $this;
    }

    public function setManifestUrl($url)
    {
        $this->manifestUrl = $url;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentLocalVersion(Updater $updater)
    {
        return $this->localVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function download(Updater $updater)
    {
        $version = $this->getCurrentRemoteVersion($updater);
        if ($version === false) {
            throw new RuntimeException('No remote versions found');
        }

        $versionInfo = $this->getAvailableVersions();
        if (!isset($versionInfo[$version])) {
            throw new RuntimeException(sprintf('Failed to find manifest item for version %s', $version));
        }

        $context = stream_context_create(['http' => ['timeout' => $this->downloadTimeout]]);
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler(array($updater, 'throwHttpRequestException'));
        $fileContents = file_get_contents($versionInfo[$version]['url'], false, $context);
        restore_error_handler();

        if ($fileContents === false) {
            throw new HttpRequestException(sprintf('Failed to download file from URL: %s', $versionInfo[$version]['url']));
        }

        $tmpFilename = $updater->getTempPharFile();
        if (file_put_contents($tmpFilename, $fileContents) === false) {
            throw new RuntimeException(sprintf('Failed to write file: %s', $tmpFilename));
        }

        $tmpSha = hash_file($this->hashAlgo, $tmpFilename);
        if ($tmpSha !== $versionInfo[$version][$this->hashAlgo]) {
            unlink($tmpFilename);
            throw new RuntimeException(
                sprintf(
                    '%s verification failed: expected %s, actual %s',
                    strtoupper($this->hashAlgo),
                    $versionInfo[$version][$this->hashAlgo],
                    $tmpSha
                )
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentRemoteVersion(Updater $updater)
    {
        $versions = array_keys($this->getAvailableVersions());
        if (!$this->allowMajor) {
            $versions = $this->filterByLocalMajorVersion($versions);
        }
        if (!$this->ignorePhpReq) {
            $versions = $this->filterByPhpVersion($versions);
        }

        $versionParser = new VersionParser($versions);

        $mostRecent = $versionParser->getMostRecentStable();

        // Look for unstable updates if explicitly allowed, or if the local
        // version is already unstable and there is no new stable version.
        if (true === $this->requireUnstable) {
            $mostRecent = $versionParser->getMostRecentUnstable();
        } elseif ($this->allowUnstable || ($versionParser->isUnstable($this->localVersion)
        && version_compare($mostRecent, $this->localVersion, '<'))) {
            $mostRecent = $versionParser->getMostRecentAll();
        }

        return version_compare($mostRecent, $this->localVersion, '>') ? $mostRecent : false;
    }

    /**
     * Find update/upgrade notes for the new remote version.
     *
     * @param Updater $updater
     * @param bool $useBaseNote Return main note if no version specific update notes found.
     *
     * @return string|false A string if notes are found, or false otherwise.
     */
    public function getUpdateNotes(Updater $updater, $useBaseNote = false)
    {
        $versionInfo = $this->getRemoteVersionInfo($updater);
        if (empty($versionInfo['updating'])) {
            return false;
        }
        $localVersion = $this->getCurrentLocalVersion($updater);
        $items = isset($versionInfo['updating'][0]) ? $versionInfo['updating'] : [$versionInfo['updating']];
        foreach ($items as $updating) {
            if (!isset($updating['notes'])) {
                continue;
            } elseif (isset($updating['hide from'])
            && version_compare($localVersion, $updating['hide from'], '>=')) {
                continue;
            } elseif (isset($updating['show from'])
            && version_compare($localVersion, $updating['show from'], '<')) {
                continue;
            }

            return $updating['notes'];
        }

        if (true === $useBaseNote && !empty($versionInfo['notes'])) {
            return $versionInfo['notes'];
        }

        return false;
    }

    /**
     * Gets available versions to update to.
     *
     * @return array  An array keyed by the version name, whose elements are arrays
     *                containing version information ('name', $this->hashAlgo, and 'url').
     */
    private function getAvailableVersions()
    {
        if (isset($this->availableVersions)) {
            return $this->availableVersions;
        }

        $this->availableVersions = array();
        foreach ($this->retrieveManifest() as $key => $item) {
            if ($missing = array_diff($this->requiredKeys, array_keys($item))) {
                throw new RuntimeException(
                    sprintf(
                        'Manifest item %s missing required key(s): %s',
                        $key,
                        implode(',', $missing)
                    )
                );
            }
            $this->availableVersions[$item['version']] = $item;
        }
        return $this->availableVersions;
    }

    /**
     * Download and decode the JSON manifest file.
     *
     * @return array
     */
    private function retrieveManifest()
    {
        if (isset($this->manifest)) {
            return $this->manifest;
        }

        if (!isset($this->manifest)) {
            $context = stream_context_create(['http' => ['timeout' => $this->manifestTimeout]]);
            $manifestContents = file_get_contents($this->manifestUrl, false, $context);
            if ($manifestContents === false) {
                throw new RuntimeException(sprintf('Failed to download manifest: %s', $this->manifestUrl));
            }

            $this->manifest = json_decode($manifestContents, true, 512, JSON_OBJECT_AS_ARRAY);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new JsonParsingException(
                    'Error parsing manifest file'
                    . (function_exists('json_last_error_msg') ? ': ' . json_last_error_msg() : '')
                );
            }
        }

        return $this->manifest;
    }

    /**
     * Get version information for the latest remote version.
     *
     * @param Updater $updater
     *
     * @return array
     */
    private function getRemoteVersionInfo(Updater $updater)
    {
        $version = $this->getCurrentRemoteVersion($updater);
        if ($version === false) {
            throw new RuntimeException('No remote versions found');
        }
        $versionInfo = $this->getAvailableVersions();
        if (!isset($versionInfo[$version])) {
            throw new RuntimeException(sprintf('Failed to find manifest item for version %s', $version));
        }
        return $versionInfo[$version];
    }

    /**
     * Filter a list of versions to those that match the current local version.
     *
     * @param string[] $versions
     *
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

        /**
     * Filter a list of versions to those that allow the current PHP version.
     *
     * @param string[] $versions
     *
     * @return string[]
     */
    private function filterByPhpVersion(array $versions)
    {
        $versionInfo = $this->getAvailableVersions();
        return array_filter($versions, function ($version) use ($versionInfo) {
            if (isset($versionInfo[$version]['php']['min'])
                && version_compare(PHP_VERSION, $versionInfo[$version]['php']['min'], '<')) {
                return false;
            } elseif (isset($versionInfo[$version]['php']['max'])
                && version_compare(PHP_VERSION, $versionInfo[$version]['php']['max'], '>')) {
                return false;
            }
            return true;
        });
    }
}
