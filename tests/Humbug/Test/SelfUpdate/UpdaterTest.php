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
use Humbug\SelfUpdate\Strategy\StrategyInterface;

class UpdaterTest extends \PHPUnit_Framework_TestCase
{

    private $files;

    /** @var Updater */
    private $updater;

    private $tmp;

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
        $this->updater->getStrategy()->setPharUrl('http://www.example.com');
        $this->assertEquals($this->updater->getStrategy()->getPharUrl(), 'http://www.example.com');

        $this->updater->getStrategy()->setPharUrl('https://www.example.com');
        $this->assertEquals($this->updater->getStrategy()->getPharUrl(), 'https://www.example.com');
    }

    public function testSetPharUrlThrowsExceptionOnInvalidUrl()
    {
        $this->setExpectedException('Humbug\\SelfUpdate\\Exception\\InvalidArgumentException');
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
        $this->setExpectedException('Humbug\\SelfUpdate\\Exception\\InvalidArgumentException');
        $this->updater->getStrategy()->setVersionUrl('silly:///home/padraic');
    }

    public function testCanDetectNewRemoteVersionAndStoreVersions()
    {
        $this->updater->getStrategy()->setVersionUrl('file://' . $this->files . '/good.version');
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
        $this->updater->getStrategy()->setVersionUrl('file://' . $this->files . '/empty.version');
        $this->assertTrue($this->updater->hasUpdate());
    }

    public function testThrowsExceptionOnInvalidRemoteVersion()
    {
        $this->setExpectedException(
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
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('This test requires the openssl extension to run.');
        }

        $this->createTestPharAndKey();
        $this->assertEquals('old', $this->getPharOutput($this->tmp . '/old.phar'));

        $updater = new Updater($this->tmp . '/old.phar');
        $updater->getStrategy()->setPharUrl('file://' . $this->files . '/build/new.phar');
        $updater->getStrategy()->setVersionUrl('file://' . $this->files . '/build/new.version');
        $this->assertTrue($updater->update());
        $this->assertEquals('new', $this->getPharOutput($this->tmp . '/old.phar'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testUpdatePharFailsIfCurrentPublicKeyEmpty()
    {
        //$this->markTestSkipped('Segmentation fault at present under PHP');
        copy($this->files . '/build/badkey.phar', $this->tmp . '/old.phar');
        chmod($this->tmp . '/old.phar', 0755);
        copy($this->files . '/build/badkey.phar.pubkey', $this->tmp . '/old.phar.pubkey');

        $updater = new Updater($this->tmp . '/old.phar');
        $updater->getStrategy()->setPharUrl('file://' . $this->files . '/build/new.phar');
        $updater->getStrategy()->setVersionUrl('file://' . $this->files . '/build/new.version');

        $this->setExpectedException('UnexpectedValueException');
        $updater->update();
    }

    /**
     * @runInSeparateProcess
     */
    public function testUpdatePharFailsIfCurrentPublicKeyInvalid()
    {
        $this->markTestIncomplete('Segmentation fault at present under PHP');
        /** Should be similar to testUpdatePharFailsIfCurrentPublicKeyEmpty with
            corrupt or truncated public key */
    }

    /**
     * @runInSeparateProcess
     */
    public function testUpdatePharFailsOnExpectedSignatureMismatch()
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('This test requires the openssl extension to run.');
        }

        $this->createTestPharAndKey();
        $this->assertEquals('old', $this->getPharOutput($this->tmp . '/old.phar'));

        /** Signature check should fail with invalid signature by a different privkey */
        $this->setExpectedException('UnexpectedValueException');

        $updater = new Updater($this->tmp . '/old.phar');
        $updater->getStrategy()->setPharUrl('file://' . $this->files . '/build/badsig.phar');
        $updater->getStrategy()->setVersionUrl('file://' . $this->files . '/build/badsig.version');
        $updater->update();
    }

    /**
     * @runInSeparateProcess
     */
    public function testUpdatePharFailsIfDownloadPharIsUnsignedWhenExpected()
    {
        $this->createTestPharAndKey();
        $updater = new Updater($this->tmp . '/old.phar');
        $updater->getStrategy()->setPharUrl('file://' . $this->files . '/build/nosig.phar');
        $updater->getStrategy()->setVersionUrl('file://' . $this->files . '/build/nosig.version');

        /** If newly download phar lacks an expected signature, an exception should be thrown */
        $this->setExpectedException('Humbug\\SelfUpdate\\Exception\\RuntimeException');
        $updater->update();
    }

    public function testSetBackupPathSetsThePathWhenTheDirectoryExistsAndIsWriteable()
    {
        $this->createTestPharAndKey();
        $updater = new Updater($this->tmp . '/old.phar');
        $updater->setBackupPath($this->tmp . '/backup.phar');
        $res = $updater->getBackupPath();
        $this->assertEquals($this->tmp . '/backup.phar', $res);
    }

    public function testSetRestorePathSetsThePathWhenTheDirectoryExistsAndIsWriteable()
    {
        $this->createTestPharAndKey();
        $updater = new Updater($this->tmp . '/old.phar');
        $updater->setRestorePath($this->tmp . '/backup.phar');
        $res = $updater->getRestorePath();
        $this->assertEquals($this->tmp . '/backup.phar', $res);
    }

    /**
     * Custom Strategies
     */

    public function testCanSetCustomStrategyObjects()
    {
        $this->updater->setStrategyObject(new FooStrategy);
        $this->assertTrue($this->updater->getStrategy() instanceof FooStrategy);
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

class FooStrategy implements StrategyInterface
{
    public function download(Updater $updater)
    {
    }
    public function getCurrentRemoteVersion(Updater $updater)
    {
    }
    public function getCurrentLocalVersion(Updater $updater)
    {
    }
}
