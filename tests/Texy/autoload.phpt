<?php

/**
 * Test: Blocks
 */

use Texy\Texy;

require __DIR__ . '/../../src/autoloader.php';

$texy = new Texy;

// No checks, just do something complex.
$texy->process(file_get_contents(__DIR__ . '/sources/syntax.texy'));

