<?php

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->exclude('vendor')
    ->exclude('tests/Humbug/Test/SelfUpdate/_files')
    ->in(__DIR__);

return Symfony\CS\Config\Config::create()
    ->level('psr2')
    ->finder($finder);