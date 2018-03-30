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

class UpdaterGithubStrategyTest extends \PHPUnit_Framework_TestCase
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
        $this->updater = new Updater($this->files . '/test.phar', false, Updater::STRATEGY_GITHUB);
    }

    public function teardown()
    {
        $this->deleteTempPhars();
    }

    public function testConstruction()
    {
        $updater = new Updater(null, false, Updater::STRATEGY_GITHUB);
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
        $this->setExpectedException(
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

        $updater = new Updater($this->tmp . '/old.phar');
        $updater->setStrategyObject(new GithubTestStrategy);
        $updater->getStrategy()->setPharName('new.phar');
        $updater->getStrategy()->setPackageName('humbug/test-phar');
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
        @unlink($this->tmp . '/packages.json');
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
        file_put_contents($this->tmp . '/packages.json', json_encode(array(
            'packages' => array(
                'humbug/test-phar' => array(
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
        return 'file://' . sys_get_temp_dir() . '/packages.json';
    }
}
