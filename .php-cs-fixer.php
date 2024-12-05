<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->notPath('vendor');

$config = new PhpCsFixer\Config();
$config->setRiskyAllowed(true);
$config->setRules([
    // Rulesets
    '@PSR2' => true,
    '@PhpCsFixer' => true,
    '@PhpCsFixer:risky' => true,
    '@PHP56Migration:risky' => true,
    '@PHPUnit57Migration:risky' => true
]);
$config->setFinder($finder);
return $config;