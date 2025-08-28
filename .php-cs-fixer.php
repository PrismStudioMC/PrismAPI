<?php

require_once __DIR__ . '/vendor/autoload.php';

$rules = [
    '@PSR12' => true,
];

$result = (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules($rules)
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(['src'])
    );

echo "PHP CS Fixer configuration generated successfully.\n";
