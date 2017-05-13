<?php

namespace Humbug\Test\SelfUpdate;

use Humbug\SelfUpdate\Updater;
use Humbug\SelfUpdate\Strategy\ManifestStrategy;
use PHPUnit\Framework\TestCase;

class UpdaterManifestStrategyTest extends TestCase
{

    /**
     * @var string
     */
    private $files;

    /**
     * @var Updater
     */
    private $updater;

    /**
     * @var string
     */
    private $manifestFile;

    /**
     * @var string
     */
    private $tmp;

    /**
     * @inheritdoc
     */
    public function setup()
    {
        $this->tmp = sys_get_temp_dir();
        $this->files = __DIR__ . '/_files';
        $this->updater = new Updater($this->files . '/test.phar', false);
        $this->manifestFile = $this->files . '/manifest.json';
    }

    /**
     * @inheritdoc
     */
    public function teardown()
    {
        @unlink($this->tmp . '/test.phar');
        @unlink($this->tmp . '/backup.phar');
    }
    
    public function testGetLocalVersion()
    {
        $strategy = new ManifestStrategy('1.0.0', $this->manifestFile);
        $this->assertEquals('1.0.0', $strategy->getCurrentLocalVersion($this->updater));
    }

    public function testSuggestMostRecentStable()
    {
        $strategy = new ManifestStrategy('1.0.0', $this->manifestFile);
        $this->assertEquals('1.2.0', $strategy->getCurrentRemoteVersion($this->updater));
    }

    public function testSuggestNewestUnstable()
    {
        $strategy = new ManifestStrategy('1.0.0', $this->manifestFile, false, true);
        $this->assertEquals('1.3.0-beta', $strategy->getCurrentRemoteVersion($this->updater));
    }

    public function testSuggestNewestStableFromUnstable()
    {
        $strategy = new ManifestStrategy('1.0.0-beta', $this->manifestFile);
        $this->assertEquals('1.2.0', $strategy->getCurrentRemoteVersion($this->updater));
    }

    public function testSuggestNewestUnstableFromUnstable()
    {
        $strategy = new ManifestStrategy('1.2.9-beta', $this->manifestFile);
        $this->assertEquals('1.3.0-beta', $strategy->getCurrentRemoteVersion($this->updater));
    }

    public function testUpdate()
    {
        copy($this->files . '/test.phar', $this->tmp . '/test.phar');
        $updater = new Updater($this->tmp . '/test.phar', false);
        $strategy = new ManifestStrategy('1.0.0', $this->manifestFile);
        $updater->setStrategyObject($strategy);
        $updater->setBackupPath($this->tmp . '/backup.phar');
        $cwd = getcwd();
        chdir(__DIR__);
        $this->assertTrue($updater->update());
        chdir($cwd);
    }

}
