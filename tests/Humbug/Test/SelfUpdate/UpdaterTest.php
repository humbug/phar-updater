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

class UpdaterTest extends \PHPUnit_Framework_TestCase
{

    private $files;

    private $updater;

    private $tmp;

    private $phars = array();

    public function setup()
    {
        $this->tmp = sys_get_temp_dir();
        $this->files = __DIR__ . '/_files';

        $this->updater = new Updater($this->files . '/test.phar');
    }

    public function teardown()
    {
        $this->deleteTempPhars();
    }

    public function testConstruction()
    {
        // with key
        $updater = new Updater($this->files . '/test.phar');
        $this->assertEquals($updater->getLocalPharFile(), $this->files . '/test.phar');
        $this->assertEquals($updater->getLocalPubKeyFile(), $this->files . '/test.phar.pubkey');

        // without key
        $updater = new Updater($this->files . '/test.phar', false);
        $this->assertEquals($updater->getLocalPharFile(), $this->files . '/test.phar');
        $this->assertNull($updater->getLocalPubKeyFile());

        // no name - detect running console app
        $updater = new Updater(null, false);
        $this->assertStringEndsWith(
            'phpunit.phar',
            basename($updater->getLocalPharFile(), '.phar') . '.phar'
        );
    }

    public function testConstructorThrowsExceptionIfPubKeyNotExistsButFlagTrue()
    {
        $this->setExpectedException('Humbug\\SelfUpdate\\Exception\\RuntimeException');
        $updater = new Updater($this->files . '/test-nopubkey.phar');
    }

    public function testConstructorAncilliaryValues()
    {
        $this->assertEquals($this->updater->getLocalPharFileBasename(), 'test');
        $this->assertEquals($this->updater->getTempDirectory(), $this->files);
    }

    public function testSetPharUrlWithUrl()
    {
        $this->updater->setPharUrl('http://www.example.com');
        $this->assertEquals($this->updater->getPharUrl(), 'http://www.example.com');

        $this->updater->setPharUrl('https://www.example.com');
        $this->assertEquals($this->updater->getPharUrl(), 'https://www.example.com');
    }

    public function testSetPharUrlThrowsExceptionOnInvalidUrl()
    {
        $this->setExpectedException('Humbug\\SelfUpdate\\Exception\\InvalidArgumentException');
        $this->updater->setPharUrl('silly:///home/padraic');
    }

    public function testSetVersionUrlWithUrl()
    {
        $this->updater->setVersionUrl('http://www.example.com');
        $this->assertEquals($this->updater->getVersionUrl(), 'http://www.example.com');

        $this->updater->setVersionUrl('https://www.example.com');
        $this->assertEquals($this->updater->getVersionUrl(), 'https://www.example.com');
    }

    public function testSetVersionUrlThrowsExceptionOnInvalidUrl()
    {
        $this->setExpectedException('Humbug\\SelfUpdate\\Exception\\InvalidArgumentException');
        $this->updater->setVersionUrl('silly:///home/padraic');
    }

    public function testCanDetectNewRemoteVersionAndStoreVersions()
    {
        $this->updater->setVersionUrl('file://' . $this->files . '/good.version');
        $this->assertTrue($this->updater->hasUpdate());
        $this->assertEquals('da39a3ee5e6b4b0d3255bfef95601890afd80709', $this->updater->getOldVersion());
        $this->assertEquals('1af1b9c94dea1ff337587bfa9109f1dad1ec7b9b', $this->updater->getNewVersion());
    }

    public function testThrowsExceptionOnEmptyRemoteVersion()
    {
        $this->setExpectedException(
            'Humbug\\SelfUpdate\\Exception\\HttpRequestException',
            'Version request returned empty response'
        );
        $this->updater->setVersionUrl('file://' . $this->files . '/empty.version');
        $this->assertTrue($this->updater->hasUpdate());
    }

    public function testThrowsExceptionOnInvalidRemoteVersion()
    {
        $this->setExpectedException(
            'Humbug\\SelfUpdate\\Exception\\HttpRequestException',
            'Version request returned incorrectly formatted response'
        );
        $this->updater->setVersionUrl('file://' . $this->files . '/bad.version');
        $this->assertTrue($this->updater->hasUpdate());
    }

    public function testUpdatesPhar()
    {
        copy($this->files . '/build/old.phar', $this->tmp . '/old.phar');
        copy($this->files . '/build/old.phar.pubkey', $this->tmp . '/old.phar.pubkey');
        $this->assertEquals('old', $this->getPharOutput($this->tmp . '/old.phar'));

        $updater = new Updater($this->tmp . '/old.phar');
        $updater->setPharUrl('file://' . $this->files . '/build/new.phar');
        $updater->setVersionUrl('file://' . $this->files . '/build/new.version');
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
        @unlink($this->tmp . '/old.1c7049180abee67826d35ce308c38272242b64b8.phar');
    }
    
}