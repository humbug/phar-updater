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
    /**
     * @var array
     */
    private static $requiredKeys = array('sha1', 'version', 'url');

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
     * ManifestStrategy constructor.
     *
     * @param string $localVersion  The local version.
     * @param string $manifestUrl   The URL to a JSON manifest file. The
     *                              manifest contains an array of objects, each
     *                              containing a 'version', 'sha1', and 'url'.
     * @param bool   $allowMajor    Whether to allow updating between major
     *                              versions.
     * @param bool   $allowUnstable Whether to allow updating to an unstable
     *                              version. Ignored if $localVersion is unstable
     *                              and there are no new stable versions.
     */
    public function __construct($localVersion, $manifestUrl, $allowMajor = false, $allowUnstable = false)
    {
        $this->localVersion = $localVersion;
        $this->manifestUrl = $manifestUrl;
        $this->allowMajor = $allowMajor;
        $this->allowUnstable = $allowUnstable;
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

        $fileContents = file_get_contents($versionInfo[$version]['url']);
        if ($fileContents === false) {
            throw new HttpRequestException(sprintf('Failed to download file from URL: %s', $versionInfo[$version]['url']));
        }

        $tmpFilename = $updater->getTempPharFile();
        if (file_put_contents($tmpFilename, $fileContents) === false) {
            throw new RuntimeException(sprintf('Failed to write file: %s', $tmpFilename));
        }

        $tmpSha = sha1_file($tmpFilename);
        if ($tmpSha !== $versionInfo[$version]['sha1']) {
            unlink($tmpFilename);
            throw new RuntimeException(
                sprintf(
                    'SHA-1 verification failed: expected %s, actual %s',
                    $versionInfo[$version]['sha1'],
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

        $versionParser = new VersionParser($versions);

        $mostRecent = $versionParser->getMostRecentStable();

        // Look for unstable updates if explicitly allowed, or if the local
        // version is already unstable and there is no new stable version.
        if ($this->allowUnstable || ($versionParser->isUnstable($this->localVersion) && version_compare($mostRecent, $this->localVersion, '<'))) {
            $mostRecent = $versionParser->getMostRecentAll();
        }

        return version_compare($mostRecent, $this->localVersion, '>') ? $mostRecent : false;
    }

    /**
     * Gets available versions to update to.
     *
     * @return array  An array keyed by the version name, whose elements are arrays
     *                containing version information ('name', 'sha1', and 'url').
     */
    private function getAvailableVersions()
    {
        if (isset($this->availableVersions)) {
            return $this->availableVersions;
        }

        $this->availableVersions = array();
        foreach ($this->retrieveManifest() as $key => $item) {
            if ($missing = array_diff(self::$requiredKeys, array_keys($item))) {
                throw new RuntimeException(sprintf('Manifest item %s missing required key(s): %s', $key, implode(',', $missing)));
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
            $manifestContents = file_get_contents($this->manifestUrl);
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
}
