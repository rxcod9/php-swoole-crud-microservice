#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';

use OpenApi\Generator;

$output = __DIR__ . '/../public/swagger.json';

// ✅ instantiate generator and call generate()
$generator = new Generator();
print "Generating Swagger JSON... \n";

$openapi = $generator->generate([__DIR__ . '/../src/']);

file_put_contents(
    $output,
    $openapi->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "✅ Swagger JSON generated at $output\n";
