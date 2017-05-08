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

use Humbug\SelfUpdate\Updater;
use Humbug\SelfUpdate\Strategy\Sha256Strategy;
use PHPUnit\Framework\TestCase;

class UpdaterSha256StrategyTest extends TestCase
{

    private $files;

    /** @var Updater */
    private $updater;

    private $tmp;

    private $data;

    public function setup()
    {
        $this->tmp = sys_get_temp_dir();
        $this->files = __DIR__ . '/_files';
        $this->updater = new Updater($this->files . '/test.phar', true, Updater::STRATEGY_SHA256);
    }

    public function teardown()
    {
        $this->deleteTempPhars();
    }

    public function testConstruction()
    {
        $updater = new Updater(null, false, Updater::STRATEGY_SHA256);
        $this->assertTrue(
            $updater->getStrategy() instanceof Sha256Strategy
        );
    }


    public function testGetCurrentLocalVersion()
    {
        $this->assertEquals(
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            $this->updater->getStrategy()->getCurrentLocalVersion($this->updater)
        );
    }

    public function testSetPharUrlWithUrl()
    {
        $this->updater->getStrategy()->setPharUrl('http://www.example.com');
        $this->assertEquals($this->updater->getStrategy()->getPharUrl(), 'http://www.example.com');

        $this->updater->getStrategy()->setPharUrl('https://www.example.com');
        $this->assertEquals($this->updater->getStrategy()->getPharUrl(), 'https://www.example.com');
    }

    public function testSetPharUrlThrowsExceptionOnInvalidUrl()
    {
        $this->expectException('Humbug\\SelfUpdate\\Exception\\InvalidArgumentException');
        $this->updater->getStrategy()->setPharUrl('silly:///home/padraic');
    }

    public function testSetVersionUrlWithUrl()
    {
        $this->updater->getStrategy()->setVersionUrl('http://www.example.com');
        $this->assertEquals($this->updater->getStrategy()->getVersionUrl(), 'http://www.example.com');

        $this->updater->getStrategy()->setVersionUrl('https://www.example.com');
        $this->assertEquals($this->updater->getStrategy()->getVersionUrl(), 'https://www.example.com');
    }

    public function testSetVersionUrlThrowsExceptionOnInvalidUrl()
    {
        $this->expectException('Humbug\\SelfUpdate\\Exception\\InvalidArgumentException');
        $this->updater->getStrategy()->setVersionUrl('silly:///home/padraic');
    }

    public function testCanDetectNewRemoteVersionAndStoreVersions()
    {
        $this->updater->getStrategy()->setVersionUrl('file://' . $this->files . '/good.sha256.version');
        $this->assertTrue($this->updater->hasUpdate());
        $this->assertEquals(
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            $this->updater->getOldVersion()
        );
        $this->assertEquals(
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b858', //5 => 8
            $this->updater->getNewVersion()
        );
    }

    public function testThrowsExceptionOnEmptyRemoteVersion()
    {
        $this->expectException(
            'Humbug\\SelfUpdate\\Exception\\HttpRequestException',
            'Version request returned empty response'
        );
        $this->updater->getStrategy()->setVersionUrl('file://' . $this->files . '/empty.version');
        $this->assertTrue($this->updater->hasUpdate());
    }

    public function testThrowsExceptionOnInvalidRemoteVersion()
    {
        $this->expectException(
            'Humbug\\SelfUpdate\\Exception\\HttpRequestException',
            'Version request returned incorrectly formatted response'
        );
        $this->updater->getStrategy()->setVersionUrl('file://' . $this->files . '/bad.version');
        $this->assertTrue($this->updater->hasUpdate());
    }

    /**
     * @runInSeparateProcess
     */
    public function testUpdatePhar()
    {
        $this->createTestPharAndKey();
        $this->assertEquals('old', $this->getPharOutput($this->tmp . '/old.phar'));

        $updater = new Updater($this->tmp . '/old.phar', true, Updater::STRATEGY_SHA256);
        $updater->getStrategy()->setPharUrl('file://' . $this->files . '/build/new.phar');
        $updater->getStrategy()->setVersionUrl('file://' . $this->files . '/build/new.sha256.version');
        $this->assertTrue($updater->update());
        $this->assertEquals('new', $this->getPharOutput($this->tmp . '/old.phar'));
    }

    /**
     * Helpers
     */

    private function getPharOutput($path)
    {
        return exec('php ' . escapeshellarg($path));
    }

    private function deleteTempPhars()
    {
        @unlink($this->tmp . '/old.phar');
        @unlink($this->tmp . '/old.phar.pubkey');
        @unlink($this->tmp . '/old.phar.temp.pubkey');
        @unlink($this->tmp . '/old-old.phar');
    }

    private function createTestPharAndKey()
    {
        copy($this->files.'/build/old.phar', $this->tmp.'/old.phar');
        chmod($this->tmp.'/old.phar', 0755);
        copy(
            $this->files.'/build/old.phar.pubkey',
            $this->tmp.'/old.phar.pubkey'
        );
    }

}
