<?php

$dirs = array_filter(
    [__DIR__.'/src', __DIR__.'/tests', __DIR__.'/migrations'],
    'is_dir',
);

$finder = (new PhpCsFixer\Finder())->in($dirs);

return (new PhpCsFixer\Config())
    ->setRules(['@Symfony' => true, '@PHP84Migration' => true, 'declare_strict_types' => true])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
