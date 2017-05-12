<?php
/**
 * Humbug
 *
 * @category   Humbug
 * @package    Humbug
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/phar-updater/blob/master/LICENSE New BSD License
 *
 * This class is partially patterned after Composer's version parser.
 */

namespace Humbug\SelfUpdate;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser as Parser;

class VersionParser
{

    /**
     * @var array
     */
    private $versions;

    /**
     * @var Composer\VersionParser
     */
    private $parser;

    /**
     * @var string
     */
    const GIT_DATA_MATCH = '/.*(-\d+-g[[:alnum:]]{7})$/';

    /**
     * @param array $versions
     */
    public function __construct(array $versions = array())
    {
        $this->versions = $versions;
        $this->parser = new Parser; 
    }

    /**
     * Get the most recent stable numbered version from versions passed to
     * constructor (if any)
     *
     * @return string
     */
    public function getMostRecentStable()
    {
        return $this->selectRecentStable();
    }

    /**
     * Get the most recent unstable numbered version from versions passed to
     * constructor (if any)
     *
     * @return string
     */
    public function getMostRecentUnStable()
    {
        return $this->selectRecentUnstable();
    }

    /**
     * Get the most recent stable or unstable numbered version from versions passed to
     * constructor (if any)
     *
     * @return string
     */
    public function getMostRecentAll()
    {
        return $this->selectRecentAll();
    }

    /**
     * Checks if given version string represents a stable numbered version
     *
     * @param string $version
     * @return bool
     */
    public function isStable($version)
    {
        return $this->stable($version);
    }

    /**
     * Checks if given version string represents a 'pre-release' version, i.e.
     * it's unstable but not development level.
     *
     * @param string $version
     * @return bool
     */
    public function isPreRelease($version)
    {
        return !$this->stable($version) && !$this->development($version);
    }

    /**
     * Checks if given version string represents an unstable or dev-level
     * numbered version
     *
     * @param string $version
     * @return bool
     */
    public function isUnstable($version)
    {
        return !$this->stable($version);
    }

    /**
     * Checks if given version string represents a dev-level numbered version
     *
     * @param string $version
     * @return bool
     */
    public function isDevelopment($version)
    {
        return $this->development($version);
    }

    /**
     * Checks if two version strings are the same normalised version.
     * 
     * @param  string
     * @param  string
     * @return bool
     */
    public static function equals($version1, $version2)
    {
        $parser = new Parser;
        return $parser->normalize(self::stripGitHash($version1))
            === $parser->normalize(self::stripGitHash($version2)); 
    }

    private function selectRecentStable()
    {
        $candidates = array();
        foreach ($this->versions as $version) {
            if (!$this->stable($version)) {
                continue;
            }
            $candidates[] = $version;
        }
        if (empty($candidates)) {
            return false;
        }
        return $this->findMostRecent($candidates);
    }

    private function selectRecentUnstable()
    {
        $candidates = array();
        foreach ($this->versions as $version) {
            if ($this->stable($version) || $this->development($version)) {
                continue;
            }
            $candidates[] = $version;
        }
        if (empty($candidates)) {
            return false;
        }
        return $this->findMostRecent($candidates);
    }

    private function selectRecentAll()
    {
        $candidates = array();
        foreach ($this->versions as $version) {
            if ($this->development($version)) {
                continue;
            }
            $candidates[] = $version;
        }
        if (empty($candidates)) {
            return false;
        }
        return $this->findMostRecent($candidates);
    }

    private function findMostRecent(array $candidates)
    {
        $sorted = Semver::rsort($candidates);
        return $sorted[0];
    }

    private function stable($version)
    {
        if ('stable' === Parser::parseStability(self::stripGitHash($version))) {
            return true;
        }
        return false;
    }

    private function development($version)
    {
        if ('dev' === Parser::parseStability(self::stripGitHash($version))) {
            return true;
        }
        return false;
    }

    private static function stripGitHash($version)
    {
        if (preg_match(self::GIT_DATA_MATCH, $version, $matches)) {
            $version = str_replace($matches[1], '-dev', $version);
        }
        return $version;
    }
}
