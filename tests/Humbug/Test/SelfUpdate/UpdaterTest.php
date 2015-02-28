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
use Humbug\SelfUpdate\Exception\RuntimeException;
use Humbug\SelfUpdate\Exception\InvalidArgumentException;
use Humbug\SelfUpdate\Exception\FilesystemException;
use Humbug\SelfUpdate\Exception\HttpRequestException;

class UpdaterTest extends \PHPUnit_Framework_TestCase
{

    private $files;

    private $updater;

    public function setup()
    {
        $this->files = __DIR__ . '/_files';
        $this->updater = new Updater($this->files . '/test.phar');
    }

    public function teardown()
    {

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
        $this->assertStringEndsWith('phpunit', $updater->getLocalPharFile());
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

    public function testCanDetectNewVersion()
    {
        $this->updater->setVersionUrl('file://' . $this->files . '/good.version');
        $this->assertTrue($this->updater->hasUpdate());
    }

    private function createTempPhars()
    {

    }

    private function deleteTempPhars()
    {

    }
    
}