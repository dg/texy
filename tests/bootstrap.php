<?php

/**
 * Test initialization and helpers.
 */


if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer update --dev`';
	exit(1);
}


if (PCRE_VERSION == 8.34 && PHP_VERSION_ID < 50513) {
	Tester\Environment::skip('PCRE 8.34 is not supported due to bug #1451');
}



// configure environment
Tester\Environment::setup();
class_alias('Tester\Assert', 'Assert');
date_default_timezone_set('Europe/Prague');
$_GET = $_POST = $_COOKIE = array();


if (extension_loaded('xdebug')) {
	xdebug_disable();
	Tester\CodeCoverage\Collector::start(__DIR__ . '/coverage.dat');
}


function test(\Closure $function)
{
	$function();
}
