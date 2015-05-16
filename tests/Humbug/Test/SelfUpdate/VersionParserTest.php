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

    public function testShouldSelectNothingFromUnstablesIfStableRequested()
    {
        $versions = ['1.0.0a', '1.0.0alpha', '1.0.0-dev', 'dev-1.0.0', '1.0.0b',
        '1.0.0beta', '1.0.0rc', '1.0.0RC'];
        $parser = new VersionParser($versions);
        $this->assertSame(false, $parser->getMostRecentStable());
    }
    
    public function testShouldSelectMostRecentVersionFromStandardSelection()
    {
        $versions = ['1.0.0', '1.0.1', '1.1.0'];
        $parser = new VersionParser($versions);
        $this->assertSame('1.1.0', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromMixedSelection()
    {
        $versions = ['1.0.0', '1.0.1', '1.1.0', '1.2.0a', '1.2.0b', '1.1.0rc'];
        $parser = new VersionParser($versions);
        $this->assertSame('1.1.0', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromPrefixedSelection()
    {
        $versions = ['v1.0.0', 'v1.0.1', 'v1.1.0'];
        $parser = new VersionParser($versions);
        $this->assertSame('v1.1.0', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromPartlyPrefixedSelection()
    {
        $versions = ['v1.0.0', 'v1.0.1', '1.1.0'];
        $parser = new VersionParser($versions);
        $this->assertSame('1.1.0', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromPatchLevels()
    {
        $versions = ['1.0.0', '1.0.0-pl2', '1.0.0-pl3', '1.0.0-pl1'];
        $parser = new VersionParser($versions);
        $this->assertSame('1.0.0-pl3', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromPatchLevels2()
    {
        $versions = ['1.0.0', '1.0.0pl2', '1.0.0pl3', '1.0.0pl1'];
        $parser = new VersionParser($versions);
        $this->assertSame('1.0.0pl3', $parser->getMostRecentStable());
    }
}