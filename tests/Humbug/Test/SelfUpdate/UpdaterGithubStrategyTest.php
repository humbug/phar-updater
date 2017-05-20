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
use Humbug\SelfUpdate\Strategy\GithubStrategy;
use PHPUnit\Framework\TestCase;

class UpdaterGithubStrategyTest extends TestCase
{
    private $files;

    /** @var Updater */
    private $updater;

    /** @var GithubStrategy */
    private $strategy;

    private $tmp;

    private $data;

    public function setUp()
    {
        $this->tmp = sys_get_temp_dir();
        $this->files = __DIR__ . '/_files';

        $this->strategy = new GithubStrategy;
        $this->updater = new Updater($this->strategy, false, $this->files . '/test.phar');
    }

    public function tearDown()
    {
        $this->deleteTempPhars();
    }

    public function testConstruction()
    {
        $updater = new Updater($this->strategy, false);
        $this->assertTrue(
            $updater->getStrategy() instanceof GithubStrategy
        );
    }

    public function testSetCurrentLocalVersion()
    {
        $this->updater->getStrategy()->setCurrentLocalVersion('1.0');
        $this->assertEquals(
            '1.0',
            $this->updater->getStrategy()->getCurrentLocalVersion($this->updater)
        );
    }

    public function testSetPharName()
    {
        $this->updater->getStrategy()->setPharName('foo.phar');
        $this->assertEquals(
            'foo.phar',
            $this->updater->getStrategy()->getPharName()
        );
    }

    public function testSetPackageName()
    {
        $this->updater->getStrategy()->setPackageName('foo/bar');
        $this->assertEquals(
            'foo/bar',
            $this->updater->getStrategy()->getPackageName()
        );
    }

    public function testSetStability()
    {
        $this->assertEquals(
            'stable',
            $this->updater->getStrategy()->getStability()
        );
        $this->updater->getStrategy()->setStability('unstable');
        $this->assertEquals(
            'unstable',
            $this->updater->getStrategy()->getStability()
        );
    }

    public function testSetStabilityThrowsExceptionOnInvalidStabilityValue()
    {
        $this->expectException(
            'Humbug\\SelfUpdate\\Exception\\InvalidArgumentException'
        );
        $this->updater->getStrategy()->setStability('foo');
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

        $updater = new Updater(new GithubTestStrategy, true, $this->tmp . '/old.phar');
        $updater->getStrategy()->setPharName('new.phar');
        $updater->getStrategy()->setPackageName(''); // not used in this test
        $updater->getStrategy()->setCurrentLocalVersion('1.0.0');

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
        @unlink($this->tmp . '/releases/download/1.0.1/new.phar');
        @unlink($this->tmp . '/releases/download/1.0.1/new.phar.pubkey');
        @unlink($this->tmp . '/old.1c7049180abee67826d35ce308c38272242b64b8.phar');
        @unlink($this->tmp . '/package.json');
    }

    private function createTestPharAndKey()
    {
        copy($this->files.'/build/old.phar', $this->tmp.'/old.phar');
        chmod($this->tmp.'/old.phar', 0755);
        copy(
            $this->files.'/build/old.phar.pubkey',
            $this->tmp.'/old.phar.pubkey'
        );
        @mkdir($this->tmp.'/releases/download/1.0.1', 0755, true);
        copy($this->files.'/build/new.phar', $this->tmp.'/releases/download/1.0.1/new.phar');
        file_put_contents($this->tmp . '/package.json', json_encode(array(
            'package' => array(
                'versions' => array(
                    '1.0.1' => array(
                        'source' => array(
                            'url' => 'file://' . $this->tmp . '.git'
                        )
                    ),
                    '1.0.0' => array(
                    )
                )
            )
        )));
    }
}

class GithubTestStrategy extends GithubStrategy
{
    protected function getApiUrl()
    {
        return 'file://' . sys_get_temp_dir() . '/package.json';
    }
}
