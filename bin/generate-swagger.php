#!/usr/bin/env php
<?php
/**
 * generate-swagger.php
 *
 * This script generates a Swagger (OpenAPI) JSON documentation file
 * for the PHP project using the OpenApi\Generator.
 *
 * Usage:
 *   php generate-swagger.php
 *
 * The generated swagger.json will be saved in the /public directory.
 *
 * @author  Your Name
 * @license MIT
 */

require_once __DIR__ . '/../vendor/autoload.php';

use OpenApi\Generator;

/**
 * Path to output Swagger JSON file.
 *
 * @var string
 */
$output = __DIR__ . '/../public/swagger.json';

echo "Generating Swagger JSON...\n";

// Instantiate the OpenApi Generator
$generator = new Generator();

// Generate the OpenAPI documentation from the source directory
$openapi = $generator->generate([__DIR__ . '/../src/']);

// Save the generated Swagger JSON to the output file
file_put_contents(
    $output,
    $openapi->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "✅ Swagger JSON generated at $output\n";
