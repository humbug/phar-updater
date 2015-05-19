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

class VersionParser
{

    private $versions;

    private $modifier = '[._-]?(?:(stable|beta|b|RC|alpha|a|patch|pl|p)(?:[.-]?(\d+))?)?([.-]?dev)?';

    public function __construct(array $versions = array())
    {
        $this->versions = $versions;
    }

    public function getMostRecentStable()
    {
        return $this->selectRecentStable();
    }

    public function getMostRecentUnStable()
    {
        return $this->selectRecentUnstable();
    }

    public function getMostRecentAll()
    {
        return $this->selectRecentAll();
    }

    public function isStable($version)
    {
        return $this->stable($version);
    }

    public function isPreRelease($version)
    {
        return !$this->stable($version) && !$this->development($version);
    }

    public function isDevelopment($version)
    {
        return $this->development($version);
    }

    private function selectRecentStable()
    {
        $candidates = [];
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
        $candidates = [];
        foreach ($this->versions as $version) {
            if ($this->stable($version)) {
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
        return $this->findMostRecent($this->versions);
    }

    private function findMostRecent(array $candidates)
    {
        $candidate = null;
        $tracker = null;
        foreach ($candidates as $version) {
            if (version_compare($candidate, $version, '<')) {
                $candidate = $version;
            }
        }
        return $candidate;
    }

    private function stable($version)
    {
        $version = preg_replace('{#.+$}i', '', $version);
        if ($this->development($version)) {
            return false;
        }
        preg_match('{'.$this->modifier.'$}i', strtolower($version), $match);
        if (!empty($match[3])) {
            return false;
        }
        if (!empty($match[1])) {
            if ('beta' === $match[1] || 'b' === $match[1]
            || 'alpha' === $match[1] || 'a' === $match[1]
            || 'rc' === $match[1]) {
                return false;
            }
        }
        return true;
    }

    private function development($version)
    {
        if ('dev-' === substr($version, 0, 4) || '-dev' === substr($version, -4)) {
            return true;
        }
        return false;
    }
}
