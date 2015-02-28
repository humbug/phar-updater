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

    public function setup()
    {
        $this->files = __DIR__ . '/_files';
    }

    public function teardown()
    {

    }

    public function testConstruction()
    {
        $updater = new Updater($this->files . '/test.phar');
        $this->assertEquals($updater->getLocalPharFile(), $this->files . '/test.phar');
    }
    
}