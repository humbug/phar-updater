<?php
/**
 * Humbug
 *
 * @category   Humbug
 * @package    Humbug
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/pharupdater/blob/master/LICENSE New BSD License
 */

namespace Humbug\Test\SelfUpdate;

use Humbug\SelfUpdate\VersionParser;

class VersionParserTest extends \PHPUnit_Framework_TestCase
{

    // Stable Versions

    public function testShouldSelectNothingFromUnstablesIfStableRequested()
    {
        $versions = array('1.0.0a', '1.0.0alpha', '1.0.0-dev', 'dev-1.0.0', '1.0.0b',
        '1.0.0beta', '1.0.0rc', '1.0.0RC');
        $parser = new VersionParser($versions);
        $this->assertSame(false, $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromStandardSelection()
    {
        $versions = array('1.0.0', '1.0.1', '1.1.0');
        $parser = new VersionParser($versions);
        $this->assertSame('1.1.0', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromMixedSelection()
    {
        $versions = array('1.0.0', '1.0.1', '1.1.0', '1.2.0a', '1.2.0b', '1.1.0rc');
        $parser = new VersionParser($versions);
        $this->assertSame('1.1.0', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromPrefixedSelection()
    {
        $versions = array('v1.0.0', 'v1.0.1', 'v1.1.0');
        $parser = new VersionParser($versions);
        $this->assertSame('v1.1.0', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromPartlyPrefixedSelection()
    {
        $versions = array('v1.0.0', 'v1.0.1', '1.1.0');
        $parser = new VersionParser($versions);
        $this->assertSame('1.1.0', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromPatchLevels()
    {
        $versions = array('1.0.0', '1.0.0-pl2', '1.0.0-pl3', '1.0.0-pl1');
        $parser = new VersionParser($versions);
        $this->assertSame('1.0.0-pl3', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromPatchLevels2()
    {
        $versions = array('1.0.0', '1.0.0pl2', '1.0.0pl3', '1.0.0pl1');
        $parser = new VersionParser($versions);
        $this->assertSame('1.0.0pl3', $parser->getMostRecentStable());
    }

    // Unstable

    public function testShouldSelectNothingFromUnstablesIfUnstableRequested()
    {
        $versions = array('1.0.0', '1.0.1', '1.1.0');
        $parser = new VersionParser($versions);
        $this->assertSame(false, $parser->getMostRecentUnstable());
    }

    public function testShouldSelectNothingFromStablesOrDevsIfUnstableRequested()
    {
        $versions = array('1.0.0', '1.0.1', '1.1.0-dev', 'dev-1.1.1');
        $parser = new VersionParser($versions);
        $this->assertSame(false, $parser->getMostRecentUnstable());
    }

    public function testShouldSelectMostRecentUnstableVersionFromStandardSelection()
    {
        $versions = array('1.0.0a', '1.0.0alpha', '1.0.0-dev', 'dev-1.0.0', '1.0.0b',
        '1.0.0beta', '1.0.0rc', '1.0.0RC');
        $parser = new VersionParser($versions);
        $this->assertSame('1.0.0rc', $parser->getMostRecentUnstable());
    }

    public function testShouldSelectMostRecentUnstableVersionFromMixedSelection()
    {
        $versions = array('1.0.0', '1.0.1', '1.1.0', '1.2.0a', '1.2.0b', '1.1.0rc');
        $parser = new VersionParser($versions);
        $this->assertSame('1.2.0b', $parser->getMostRecentUnstable());
    }

    public function testShouldSelectMostRecentUnstableVersionFromPrefixedSelection()
    {
        $versions = array('v1.0.0b', 'v1.0.1', 'v1.1.0');
        $parser = new VersionParser($versions);
        $this->assertSame('v1.0.0b', $parser->getMostRecentUnstable());
    }

    public function testShouldSelectMostRecentUnstableVersionFromPartlyPrefixedSelection()
    {
        $versions = array('v1.0.0b', 'v1.0.0a', '1.1.0a');
        $parser = new VersionParser($versions);
        $this->assertSame('1.1.0a', $parser->getMostRecentUnstable());
    }

    public function testShouldSelectMostRecentUnstableFromVaryingNumeralCounts()
    {
        $versions = array('1.0-dev', '1.0.0-alpha1');
        $parser = new VersionParser($versions);
        $this->assertSame('1.0.0-alpha1', $parser->getMostRecentUnstable());
    }

    // All versions (ignoring stability)

    public function testShouldSelectMostRecentIgnoringStabilityExceptDevFromPrefixedSelection()
    {
        $versions = array('v1.0.0b', 'v1.0.1', 'v1.1.0a', 'v1.2.0-dev');
        $parser = new VersionParser($versions);
        $this->assertSame('v1.1.0a', $parser->getMostRecentAll());
    }

    // Basic Version Category Checks

    public function testIsStable()
    {
        $parser = new VersionParser;
        $this->assertTrue($parser->isStable('1.0.0'));
        $this->assertFalse($parser->isStable('1.0.0b'));
        $this->assertFalse($parser->isStable('1.0.0-dev'));
    }

    public function testIsPreRelease()
    {
        $parser = new VersionParser;
        $this->assertFalse($parser->isPreRelease('1.0.0'));
        $this->assertTrue($parser->isPreRelease('1.0.0b'));
        $this->assertFalse($parser->isPreRelease('1.0.0-dev'));
    }

    public function testIsUnstable()
    {
        $parser = new VersionParser;
        $this->assertFalse($parser->isUnstable('1.0.0'));
        $this->assertTrue($parser->isUnstable('1.0.0b'));
        $this->assertTrue($parser->isUnstable('1.0.0-dev'));
    }

    public function testIsDevelopment()
    {
        $parser = new VersionParser;
        $this->assertFalse($parser->isDevelopment('1.0.0'));
        $this->assertFalse($parser->isDevelopment('1.0.0b'));
        $this->assertTrue($parser->isDevelopment('1.0.0-dev'));
    }
}
